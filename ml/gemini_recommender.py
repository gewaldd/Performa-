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
    from config import GEMINI_API_KEY, GEMINI_MODEL, GEMINI_TRAINING_TYPES
except ImportError:
    from ml.config import GEMINI_API_KEY, GEMINI_MODEL, GEMINI_TRAINING_TYPES

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
    Calls Google Gemini API using Random Forest outputs to generate training interventions.
    """
    weak_categories = rf_output.get("weak_categories", [])
    competency_classification = rf_output.get("competency_classification", {})

    def _with_rf(d: dict) -> dict:
        # Manuscript P.502-504: keep RF provenance on every output for audit.
        d.setdefault("model_version", rf_output.get("model_version", "unknown"))
        d.setdefault("overall_prediction", rf_output.get("overall_prediction", {}))
        return d

    # If no performance gaps are identified, return a structured positive summary
    if not weak_categories:
        return _with_rf({
            "employee_uid": rf_output.get("employee_uid", "UNKNOWN"),
            "evaluation_month": rf_output.get("evaluation_month", "UNKNOWN"),
            "summary": "Employee consistently meets or exceeds performance expectations across all evaluated core competencies.",
            "training_recommendations": [],
            "generated_by": "gemini_api"
        })

    api_key = os.getenv("GEMINI_API_KEY") or GEMINI_API_KEY
    genai = _load_genai()
    if not api_key or genai is None:
        return _with_rf({
            "employee_uid": rf_output.get("employee_uid", "UNKNOWN"),
            "evaluation_month": rf_output.get("evaluation_month", "UNKNOWN"),
            "summary": "AI recommendation service unavailable.",
            "error": "GEMINI_API_KEY environment variable or config parameter is not set."
            if not api_key else "google-genai SDK not installed for this Python interpreter.",
            "training_recommendations": [],
            "generated_by": "fallback"
        })

    try:
        client = genai.Client(api_key=api_key)
    except Exception as e:
        # e.g. asyncio Winsock init failure under Apache service accounts
        return _with_rf({
            "employee_uid": rf_output.get("employee_uid", "UNKNOWN"),
            "evaluation_month": rf_output.get("evaluation_month", "UNKNOWN"),
            "summary": "AI recommendation service unavailable.",
            "error": f"Gemini client init failed: {e}",
            "training_recommendations": [],
            "generated_by": "fallback"
        })
    overall = rf_output.get("overall_prediction", {})

    prompt = f"""
    You are an expert HR Performance & Development Specialist for Small & Medium Enterprises (SMEs).
    Analyze the Random Forest competency gap results for a probationary employee and generate targeted training recommendations.

    CONTEXT:
    - Industry: {industry}
    - Job Role: {job_role}
    - Overall RF prediction: {json.dumps(overall)}
    - Flagged Weak Categories (by RF + threshold audit): {json.dumps(weak_categories)}
    - Full RF Competency Classification (score/target/gap + Gini/Permutation importance): {json.dumps(competency_classification)}
    - Model version: {rf_output.get("model_version", "unknown")}

    STRICT MANUSCRIPT CONSTRAINTS:
    1. DO NOT re-classify or override any Random Forest classifications. Treat RF output as ground truth.
    2. Provide actionable, step-by-step training recommendations ONLY for categories in Flagged Weak Categories.
    3. training_type MUST be exactly one of: {json.dumps(GEMINI_TRAINING_TYPES)}.
    4. Each rationale MUST cite the recorded score-vs-target gap and, where relevant, the feature importance weight.
    5. Return STRICTLY VALID JSON adhering to the schema below. Output NO markdown formatting, NO triple backticks, and NO extra conversational text.
    6. This is decision-support only; do not state hiring/firing decisions as final.

    REQUIRED JSON OUTPUT SCHEMA:
    {{
      "summary": "Synthesized evaluation summary highlighting specific performance gaps.",
      "training_recommendations": [
        {{
          "competency_area": "attendance_punctuality",
          "training_type": "on-the-job coaching",
          "description": "Clear step-by-step recommendation for the employer and employee",
          "timeline": "2-4 weeks",
          "rationale": "Score 3.0 vs target 4.0 (gap 1.0); Gini importance 0.25 shows attendance drove the overall classification."
        }}
      ]
    }}
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
            raw_recs = data.get("training_recommendations", [])
            validated_recs = []
            allowed_types = {t.lower(): t for t in GEMINI_TRAINING_TYPES}

            for rec in raw_recs:
                area = str(rec.get("competency_area", ""))
                # Drop recommendations for non-weak categories (manuscript constraint)
                if weak_categories and area not in weak_categories:
                    continue
                raw_type = str(rec.get("training_type", "on-the-job coaching"))
                training_type = allowed_types.get(raw_type.strip().lower(), "on-the-job coaching")
                validated_recs.append({
                    "competency_area": area,
                    "training_type": training_type,
                    "description": str(rec.get("description", "")),
                    "timeline": str(rec.get("timeline", "2-4 weeks")),
                    "rationale": str(rec.get("rationale", ""))
                })

            return _with_rf({
                "employee_uid": rf_output.get("employee_uid", "UNKNOWN"),
                "evaluation_month": rf_output.get("evaluation_month", "UNKNOWN"),
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
            # the PHP bridge timeout. Fail fast to fallback; the employer can
            # resubmit after the quota window resets.
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

            return _with_rf({
                "employee_uid": rf_output.get("employee_uid", "UNKNOWN"),
                "evaluation_month": rf_output.get("evaluation_month", "UNKNOWN"),
                "summary": "AI recommendation service unavailable.",
                "error": error_msg,
                "training_recommendations": [],
                "generated_by": "fallback"
            })


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