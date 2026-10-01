"""
Performa ML Engine - Gemini API Recommender Interface
Location: ml/gemini_recommender.py
"""

import json
import os
import re
import sys
import time
import warnings

# Resolve imports regardless of cwd (CLI root, ml/ dir, Apache cwd=ml/).
_HERE = os.path.dirname(os.path.abspath(__file__))
_ROOT = os.path.dirname(_HERE)
for _p in (_HERE, _ROOT):
    if _p not in sys.path:
        sys.path.insert(0, _p)

try:
    from config import GEMINI_API_KEY, GEMINI_MODEL
except ImportError:
    from ml.config import GEMINI_API_KEY, GEMINI_MODEL

# NOTE: `google.genai` is imported lazily inside generate_recommendations().
# Top-level import pulls asyncio -> _overlapped under Apache without SystemRoot
# (WinError 10106) and kills RF inference even when no LLM call is needed.

# Suppress SDK deprecation/style warnings to keep STDOUT clean for PHP wrapper
warnings.filterwarnings("ignore")


def _load_genai():
    try:
        from google import genai as _genai
        return _genai
    except ImportError:
        return None


def generate_recommendations(rf_output: dict, job_role: str = "Probationary Employee", industry: str = "General") -> dict:
    """
    RF picks, Gemini explains.

    The intervention *choice* (competency x RF class -> training_type /
    title / description / timeline) comes from training_catalog via
    rf_output["rf_recommendations"] and is NEVER LLM-authored. The LLM
    writes ONLY the evaluation summary + one rationale sentence per pick.
    Locked RF fields pass through untouched even if the LLM alters them.

    If the LLM is unavailable (no key/SDK, quota 429, overload), the RF
    picks still ship with empty rationales as generated_by "rf_only" —
    reviewable immediately, unlike the old empty fallback.
    """
    weak_categories = rf_output.get("weak_categories", [])
    competency_classification = rf_output.get("competency_classification", {})

    def _with_rf(d: dict) -> dict:
        # Manuscript P.502-504: keep RF provenance on every output for audit.
        d.setdefault("model_version", rf_output.get("model_version", "unknown"))
        d.setdefault("overall_prediction", rf_output.get("overall_prediction", {}))
        return d

    def _uid():
        return rf_output.get("employee_uid", "UNKNOWN")

    def _month():
        return rf_output.get("evaluation_month", "UNKNOWN")

    picks = rf_output.get("rf_recommendations") or []
    if not picks and weak_categories:
        # Older RF payloads predate rf_recommendations: derive the same
        # deterministic picks from the weak list + per-competency labels.
        try:
            from training_catalog import rf_recommendations as _derive
        except ImportError:
            from ml.training_catalog import rf_recommendations as _derive
        labels = {
            c: ((competency_classification.get(c, {}) or {}).get("classification", ""))
            for c in weak_categories
        }
        picks = _derive(weak_categories, labels)

    def _rf_only(reason: str) -> dict:
        return _with_rf({
            "employee_uid": _uid(),
            "evaluation_month": _month(),
            "summary": "Random Forest training picks ready; AI rationale unavailable.",
            "error": reason,
            "training_recommendations": [
                dict(p, rationale="") for p in picks
            ],
            "generated_by": "rf_only",
        })

    # If no performance gaps are identified, return a structured positive summary
    if not weak_categories:
        return _with_rf({
            "employee_uid": _uid(),
            "evaluation_month": _month(),
            "summary": "Employee consistently meets or exceeds performance expectations across all evaluated core competencies.",
            "training_recommendations": [],
            "generated_by": "gemini_api"
        })

    api_key = os.getenv("GEMINI_API_KEY") or GEMINI_API_KEY
    genai = _load_genai()
    if not api_key or genai is None:
        return _rf_only(
            "GEMINI_API_KEY environment variable or config parameter is not set."
            if not api_key else "google-genai SDK not installed for this Python interpreter."
        )

    try:
        client = genai.Client(api_key=api_key)
    except Exception as e:
        # e.g. asyncio Winsock init failure under Apache service accounts
        return _rf_only(f"Gemini client init failed: {e}")

    overall = rf_output.get("overall_prediction", {})
    gaps = {
        c: (competency_classification.get(c, {}) or {})
        for c in weak_categories
    }

    prompt = f"""
    You are an expert HR Performance & Development Specialist for Small & Medium Enterprises (SMEs).
    A Random Forest model already CHOSE the training interventions below. DO NOT invent, remove,
    re-type, or re-classify anything. Your ONLY job is explaining text.

    CONTEXT:
    - Industry: {industry}
    - Job Role: {job_role}
    - Overall RF prediction: {json.dumps(overall)}
    - RF picks (LOCKED - repeat back exactly, add nothing): {json.dumps(picks)}
    - Competency gaps (score/target/gap/importance): {json.dumps(gaps)}
    - Model version: {rf_output.get("model_version", "unknown")}

    WRITE ONLY:
    1. "summary": 2-3 sentence evaluation summary naming the weak areas.
    2. "rationales": object mapping each competency_area to ONE sentence citing
       its recorded score-vs-target gap (and feature importance where relevant).

    Return STRICTLY VALID JSON with exactly those two keys. NO markdown
    formatting, NO triple backticks, NO extra conversational text.
    This is decision-support only; do not state hiring/firing decisions as final.
    """

    # Retry logic with exponential backoff for transient API server spikes.
    # Kept short (3 attempts, 2s base) so the web request stays inside the
    # PHP bridge timeout (~25s) instead of fataling with HTTP 500.
    max_retries = 3
    delay = 2

    for attempt in range(max_retries):
        try:
            # Use Chat interface to comply with SDK AFC recommendations and eliminate warnings
            chat = client.chats.create(model=GEMINI_MODEL)
            response = chat.send_message(prompt)

            clean_text = response.text.strip()
            if clean_text.startswith("```json"):
                clean_text = clean_text[7:]
            if clean_text.startswith("```"):
                clean_text = clean_text[3:]
            if clean_text.endswith("```"):
                clean_text = clean_text[:-3]
            clean_text = clean_text.strip()

            data = json.loads(clean_text)
            rationales = data.get("rationales", {})
            if not isinstance(rationales, dict):
                rationales = {}
            validated_recs = []

            for pick in picks:
                area = str(pick.get("competency_area", ""))
                # Defensive: picks are already weak-only; never emit others.
                if weak_categories and area not in weak_categories:
                    continue
                merged = dict(pick)
                merged["rationale"] = str(rationales.get(area, ""))
                validated_recs.append(merged)

            return _with_rf({
                "employee_uid": _uid(),
                "evaluation_month": _month(),
                "summary": str(data.get("summary", "")),
                "training_recommendations": validated_recs,
                "generated_by": "gemini_api"
            })

        except Exception as e:
            error_msg = str(e)
            # NOTE: 429/RESOURCE_EXHAUSTED is deliberately NOT retried. The free
            # tier for this model caps at ~20 requests/day, and every retry burns
            # the same pool while the "retry in Ns" hint keeps growing (30s, 58s,
            # ...). Retrying just pushes the reset further out and risks blowing
            # the PHP bridge timeout. Fail fast to rf_only: the RF picks stay
            # reviewable and the employer can regenerate rationales later.
            if "429" in error_msg or "RESOURCE_EXHAUSTED" in error_msg:
                return _rf_only(error_msg)
            retryable = ("503", "UNAVAILABLE",
                         "overloaded", "Overloaded", "timeout", "Timeout", "timed out")
            if any(s in error_msg for s in retryable) and attempt < max_retries - 1:
                # Honor the API's own "retry in Ns" hint where present,
                # capped so the web request stays inside the PHP bridge timeout.
                wait = delay
                m = re.search(r"retry in ([\d.]+)s", error_msg, re.IGNORECASE)
                if m:
                    try:
                        wait = min(float(m.group(1)) + 1.0, 25.0)
                    except ValueError:
                        pass
                time.sleep(wait)
                delay *= 2
                continue

            return _rf_only(error_msg)


if __name__ == "__main__":
    try:
        from predict import predict_competencies
    except ImportError:
        from ml.predict import predict_competencies

    if "--input-stdin" in sys.argv:
        try:
            raw_input = sys.stdin.read()
            payload = json.loads(raw_input)

            emp_uid = payload.get("employee_uid", "UNKNOWN")
            eval_month = payload.get("evaluation_month", "UNKNOWN")
            job_role = payload.get("job_role", "Probationary Employee")
            industry = payload.get("industry", "General")
            scores = payload.get("category_scores", {})

            rf_res = predict_competencies(emp_uid, eval_month, scores)
            recs = generate_recommendations(rf_res, job_role=job_role, industry=industry)
            print(json.dumps(recs))

        except Exception as err:
            print(json.dumps({
                "employee_uid": "UNKNOWN",
                "evaluation_month": "UNKNOWN",
                "summary": "Performance evaluation recorded. Training recommendations unavailable.",
                "error": f"Failed to process STDIN payload: {str(err)}",
                "training_recommendations": [],
                "generated_by": "fallback"
            }))
    else:
        sample_scores = {
            "food_safety": 1.6,
            "service_speed": 1.2,
            "customer_service": 1.1,
            "attendance": 1.4
        }
        rf_res = predict_competencies("EMP-1002", "2026-09", sample_scores)
        recs = generate_recommendations(rf_res, job_role="Food Service Worker", industry="food_service")
        print(json.dumps(recs, indent=2))