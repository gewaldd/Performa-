#!/usr/bin/env python3
"""
Performa Gemini Training Recommendation Module (Stage 3)

ALIGNS WITH MANUSCRIPT (Lines 137, 272, 528-529):
- Receives RF classification output, weak categories, job role, industry
- Generates training recommendations via Google Gemini API
- Recommendations specify: training type, target competency area, timeline
- Feature importance from RF ensures safe, explainable LLM recommendations

Three-Stage Training Recommendation Component (Line 137):
  Stage 1: Weekly KPI scores -> monthly competency summaries (features.py)
  Stage 2: RF classifies competency gaps (predict.py)
  Stage 3: RF output + weak categories -> Gemini -> recommendations (this file)
"""

import json
import os
import sys
from typing import Any, Dict, List, Optional

from config import (
    CLASS_DISPLAY_NAMES,
    CLASS_MEETS_EXPECTATIONS,
    CLASS_NEEDS_IMPROVEMENT,
    CLASS_CRITICAL_GAP,
    COMPETENCY_CATEGORIES,
    COMPETENCY_DISPLAY_NAMES,
    DEFAULT_INDUSTRY,
    GEMINI_API_KEY_ENV_VAR,
    GEMINI_MAX_OUTPUT_TOKENS,
    GEMINI_MODEL,
    GEMINI_TIMELINE_OPTIONS_WEEKS,
    GEMINI_TRAINING_TYPES,
        MODEL_VERSION,
)


def build_gemini_prompt(
    prediction: Dict[str, Any],
    job_role: str,
    industry: str,
    feature_importance: Optional[Dict[str, float]] = None,
    probation_month: int = 1,
) -> str:
    """Build the prompt for the Gemini API.

    Manuscript Line 137: "the classification output is passed to the Google
    Gemini API together with the employee's job role, industry context, and
    identified weak categories."

    Manuscript Lines 497-498, 528-529: "Feature importance enables safe LLM
    training recommendations." The RF's Gini Feature Importance is included
    so Gemini can weight competency areas appropriately.

    Args:
        prediction: Output dict from predict.predict_employee()
        job_role: Employee's job role/position title
        industry: Industry type (retail, bpo, food_service, etc.)
        feature_importance: Optional Gini importance per competency
        probation_month: Current month of probation (1-5)

    Returns:
        Formatted prompt string for Gemini API
    """
    uid = prediction.get("uid", "unknown")
    weak_categories = prediction.get("weak_categories", [])
    classification = prediction.get("competency_classification", {})
    overall_pred = prediction.get("overall_prediction")
    confidence = prediction.get("overall_confidence")

    # Build competency summary for Gemini
    competency_lines = []
    for comp in COMPETENCY_CATEGORIES:
        comp_data = classification.get(comp, {})
        cls = comp_data.get("classification", "unknown")
        score = comp_data.get("score", "?")
        target = comp_data.get("target", "?")
        display = COMPETENCY_DISPLAY_NAMES.get(comp, comp)
        cls_display = CLASS_DISPLAY_NAMES.get(cls, cls)
        marker = "  " if cls == CLASS_MEETS_EXPECTATIONS else " **"
        competency_lines.append(
            f"{marker} {display}: Score {score}/5.0 "
            f"(target: {target}) -> {cls_display}"
        )

    # Build feature importance context
    importance_text = ""
    if feature_importance:
        sorted_imp = sorted(
            feature_importance.items(), key=lambda x: x[1], reverse=True
        )
        importance_lines = [
            f"    {COMPETENCY_DISPLAY_NAMES.get(c, c)}: "
            f"{imp:.4f}"
            for c, imp in sorted_imp
        ]
        importance_text = (
            "\nFeature Importance (Gini Impurity weight):\n"
            + "\n".join(importance_lines)
            + "\n"
        )

    # Build weak categories section
    weak_text = ""
    if weak_categories:
        weak_displays = [
            COMPETENCY_DISPLAY_NAMES.get(w, w) for w in weak_categories
        ]
        weak_text = (
            f"\nWeak Categories Identified: {', '.join(weak_displays)}"
        )
    else:
        weak_text = "\nWeak Categories Identified: None (all meeting expectations)"

    # Build overall confidence context
    confidence_text = ""
    if confidence is not None:
        confidence_text = f"\nModel Confidence: {confidence:.1%}"

    # Training types (Line 137)
    training_types_str = ", ".join(GEMINI_TRAINING_TYPES)
    timeline_options_str = ", ".join(f"{t} weeks" for t in GEMINI_TIMELINE_OPTIONS_WEEKS)

    prompt = f"""You are an AI training advisor for Performa, a KPI-based
probationary employee evaluation platform for Philippine SMEs.

Employee ID: {uid}
Job Role: {job_role}
Industry: {industry}
Probation Month: {probation_month} of 5

COMPETENCY CLASSIFICATION (Random Forest + Threshold Analysis):
{chr(10).join(competency_lines)}
{importance_text}
{weak_text}
{confidence_text}
Overall RF Prediction: {CLASS_DISPLAY_NAMES.get(overall_pred, str(overall_pred)) if overall_pred else 'N/A'}

TASK: Generate a structured JSON response with training recommendations
for the employee's weak categories. For each weak category, specify:
1. training_type: One of: {training_types_str}
2. target_competency: The competency category name
3. suggested_timeline_weeks: One of: {timeline_options_str}
4. rationale: Brief explanation of why this training addresses the gap

IMPORTANT CONSTRAINTS:
- Only recommend training for categories classified as 'Needs Improvement'
  or 'Critical Gap'. Do not recommend for 'Meets Expectations'.
- If no weak categories exist, return an empty recommendations array.
- Keep rationale to 1-2 sentences.
- Use strict JSON format with these exact field names.
- Prioritize training types that are practical for small businesses.

OUTPUT FORMAT (JSON only, no additional text):
{{
  "recommendations": [
    {{
      "training_type": "workshop",
      "target_competency": "Quality of Work",
      "suggested_timeline_weeks": 4,
      "rationale": "Brief explanation here."
    }}
  ]
}}"""

    return prompt


def parse_gemini_response(
    response_text: str,
) -> List[Dict[str, Any]]:
    """Parse the JSON response from Gemini API.

    Manuscript Line 137: "specifying training type, target competency area,
    and suggested timeline for completion."

    Args:
        response_text: Raw text response from Gemini API

    Returns:
        List of recommendation dicts, each with:
        - training_type: One of GEMINI_TRAINING_TYPES
        - target_competency: Competency display name
        - suggested_timeline_weeks: Number of weeks
        - rationale: Explanation string
    """
    # Try to extract JSON from response (handle markdown wrappers)
    text = response_text.strip()

    # Remove markdown code fences if present
    if text.startswith("```"):
        # Find closing fence
        fence_end = text.find("\n```", 3)
        if fence_end > 0:
            text = text[3:fence_end]
        else:
            text = text[3:]
        text = text.strip()

    # Remove "json" prefix after opening fence
    if text.lower().startswith("json\n"):
        text = text[5:].strip()

    try:
        data = json.loads(text)
    except json.JSONDecodeError:
        # Try finding JSON object in text
        start = text.find("{")
        if start == -1:
            return []
        # Find matching close brace
        depth = 0
        end = start
        for i, ch in enumerate(text[start:], start):
            if ch == "{":
                depth += 1
            elif ch == "}":
                depth -= 1
                if depth == 0:
                    end = i + 1
                    break
        try:
            data = json.loads(text[start:end])
        except json.JSONDecodeError:
            return []

    recommendations = data.get("recommendations", [])
    if not isinstance(recommendations, list):
        return []

    # Validate each recommendation
    validated = []
    for rec in recommendations:
        if not isinstance(rec, dict):
            continue
        training_type = rec.get("training_type", "")
        target_comp = rec.get("target_competency", "")
        timeline = rec.get("suggested_timeline_weeks", 4)
        rationale = rec.get("rationale", "")

        # Validate training type
        if training_type not in GEMINI_TRAINING_TYPES:
            continue

        # Validate timeline is an integer
        try:
            timeline = int(timeline)
        except (ValueError, TypeError):
            timeline = 4

        # Validate competency name
        comp_key = None
        for key, display in COMPETENCY_DISPLAY_NAMES.items():
            if display == target_comp or key == target_comp:
                comp_key = key
                break

                        validated.append({
            "training_type": training_type,
            "target_competency": target_comp,
            "target_competency_key": comp_key or target_comp,
            "suggested_timeline_weeks": timeline,
            "rationale": rationale,
        })

    return validated


def get_training_recommendations(
    prediction: Dict[str, Any],
    job_role: str,
    industry: str,
    feature_importance: Optional[Dict[str, float]] = None,
    probation_month: int = 1,
    api_key: Optional[str] = None,
    model_name: str = GEMINI_MODEL,
    mock_response: Optional[str] = None,
) -> Dict[str, Any]:
    """Generate training recommendations via Gemini API.

    Manuscript Line 137: "The Gemini API generates a structured set of
    training recommendations in natural language, specifying training type
    (on-the-job coaching, workshop, self-directed learning, or mentoring),
    target competency area, and suggested timeline for completion."

    Manuscript Lines 497-498, 528-529: Feature importance from the RF model
    is passed to Gemini to ensure recommendations are grounded in the
    explainable model analysis, enabling safe LLM training recommendations.

    Args:
        prediction: Output from predict.predict_employee()
        job_role: Employee's job role/position title
        industry: Industry type string
        feature_importance: Gini importance per competency from RF model
        probation_month: Current probation month (1-5)
        api_key: Gemini API key (defaults to env var)
        model_name: Gemini model name
        mock_response: For testing - skip API call and use this response

    Returns:
        Dict with:
        - recommendations: List of validated recommendation dicts
        - prompt: The prompt sent to Gemini
        - model: Model name used
        - raw_response: Raw response text (for debugging)
    """
    prompt = build_gemini_prompt(
        prediction, job_role, industry,
        feature_importance, probation_month,
    )

    if mock_response is not None:
        recommendations = parse_gemini_response(mock_response)
    else:
        api_key = api_key or os.environ.get(GEMINI_API_KEY_ENV_VAR)
        if not api_key:
            raise ValueError(
                f"Gemini API key not found. Set {GEMINI_API_KEY_ENV_VAR} "
                "environment variable or pass api_key parameter."
            )

        try:
            import google.generativeai as genai
            genai.configure(api_key=api_key)
            model = genai.GenerativeModel(model_name)
            response = model.generate_content(
                prompt,
                generation_config={
                    "max_output_tokens": GEMINI_MAX_OUTPUT_TOKENS,
                    "temperature": 0.3,
                },
            )
            raw_response = response.text
        except ImportError:
            raise ImportError(
                "google-generativeai package not installed. "
                "Run: pip install google-generativeai"
            )
        except Exception as e:
            raise RuntimeError(f"Gemini API call failed: {e}")

        recommendations = parse_gemini_response(raw_response)

    return {
        "recommendations": recommendations,
        "prompt": prompt,
        "model": model_name,
        "raw_response": raw_response if mock_response is None else mock_response,
    }


def main():
    """CLI entry point - reads JSON from stdin, outputs recommendations.

    Input:
    {
        "prediction": {...},  # from predict_employee()
        "job_role": "Cashier",
        "industry": "retail",
        "feature_importance": {...},  # optional
        "probation_month": 2
    }

    Output:
    {
        "recommendations": [...],
        "model": "gemini-2.5-flash",
        ...
    }
    """
    try:
        payload = json.load(sys.stdin)
    except json.JSONDecodeError as e:
        print(json.dumps({"error": f"Invalid JSON: {e}"}))
        sys.exit(1)

    prediction = payload.get("prediction")
    if not prediction:
        print(json.dumps({"error": "Missing 'prediction' field"}))
        sys.exit(1)

    job_role = payload.get("job_role", "Unknown")
    industry = payload.get("industry", "retail")
    feature_importance = payload.get("feature_importance")
    probation_month = payload.get("probation_month", 1)
    mock_response = payload.get("mock_response")

    try:
        result = get_training_recommendations(
            prediction=prediction,
            job_role=job_role,
            industry=industry,
            feature_importance=feature_importance,
            probation_month=probation_month,
            mock_response=mock_response,
        )
        # Exclude prompt from output for brevity
        output = {
            "recommendations": result["recommendations"],
            "model": result["model"],
        }
        if mock_response is None:
            output["raw_response"] = result["raw_response"]
        json.dump(output, sys.stdout, indent=2)
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()

