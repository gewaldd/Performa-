"""
Performa ML Engine - Gemini API Recommender Interface
Location: ml/gemini_recommender.py
"""

import json
import os
import sys
import time
import warnings
from google import genai
from config import GEMINI_API_KEY

# Suppress SDK deprecation/style warnings to keep STDOUT clean for PHP wrapper
warnings.filterwarnings("ignore")


def generate_recommendations(rf_output: dict, job_role: str = "Probationary Employee", industry: str = "General") -> dict:
    """
    Calls Google Gemini API using Random Forest outputs to generate training interventions.
    """
    weak_categories = rf_output.get("weak_categories", [])
    competency_classification = rf_output.get("competency_classification", {})

    # If no performance gaps are identified, return a structured positive summary
    if not weak_categories:
        return {
            "employee_uid": rf_output.get("employee_uid", "UNKNOWN"),
            "evaluation_month": rf_output.get("evaluation_month", "UNKNOWN"),
            "summary": "Employee consistently meets or exceeds performance expectations across all evaluated core competencies.",
            "training_recommendations": [],
            "generated_by": "gemini_api"
        }

    api_key = os.getenv("GEMINI_API_KEY") or GEMINI_API_KEY
    if not api_key:
        return {
            "employee_uid": rf_output.get("employee_uid", "UNKNOWN"),
            "evaluation_month": rf_output.get("evaluation_month", "UNKNOWN"),
            "summary": "AI recommendation service unavailable.",
            "error": "GEMINI_API_KEY environment variable or config parameter is not set.",
            "training_recommendations": [],
            "generated_by": "fallback"
        }

    client = genai.Client(api_key=api_key)

    prompt = f"""
    You are an expert HR Performance & Development Specialist for Small & Medium Enterprises (SMEs).
    Analyze the Random Forest competency gap results for a probationary employee and generate targeted training recommendations.

    CONTEXT:
    - Industry: {industry}
    - Job Role: {job_role}
    - Flagged Weak Categories (by RF): {json.dumps(weak_categories)}
    - Full RF Competency Classification: {json.dumps(competency_classification)}

    STRICT MANUSCRIPT CONSTRAINTS:
    1. DO NOT re-classify or override any Random Forest classifications. Treat RF output as ground truth.
    2. Provide actionable, step-by-step training recommendations ONLY for categories in Flagged Weak Categories.
    3. Return STRICTLY VALID JSON adhering to the schema below. Output NO markdown formatting, NO triple backticks (```json), and NO extra conversational text.

    REQUIRED JSON OUTPUT SCHEMA:
    {{
      "summary": "Synthesized evaluation summary highlighting specific performance gaps.",
      "training_recommendations": [
        {{
          "competency_area": "attendance_punctuality",
          "training_type": "on-the-job coaching",
          "description": "Clear step-by-step recommendation for the employer and employee",
          "timeline": "2-4 weeks",
          "rationale": "Direct justification based on recorded score gap"
        }}
      ]
    }}
    """

    # Retry logic with exponential backoff for transient API server spikes
    max_retries = 5
    delay = 3

    for attempt in range(max_retries):
        try:
            # Use Chat interface to comply with SDK AFC recommendations and eliminate warnings
            chat = client.chats.create(model="gemini-3.6-flash")
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

            for rec in raw_recs:
                validated_recs.append({
                    "competency_area": str(rec.get("competency_area", "")),
                    "training_type": str(rec.get("training_type", "on-the-job coaching")),
                    "description": str(rec.get("description", "")),
                    "timeline": str(rec.get("timeline", "2-4 weeks")),
                    "rationale": str(rec.get("rationale", ""))
                })

            return {
                "employee_uid": rf_output.get("employee_uid", "UNKNOWN"),
                "evaluation_month": rf_output.get("evaluation_month", "UNKNOWN"),
                "summary": str(data.get("summary", "")),
                "training_recommendations": validated_recs,
                "generated_by": "gemini_api"
            }

        except Exception as e:
            error_msg = str(e)
            if ("503" in error_msg or "UNAVAILABLE" in error_msg) and attempt < max_retries - 1:
                time.sleep(delay)
                delay *= 2
                continue

            return {
                "employee_uid": rf_output.get("employee_uid", "UNKNOWN"),
                "evaluation_month": rf_output.get("evaluation_month", "UNKNOWN"),
                "summary": "AI recommendation service unavailable.",
                "error": error_msg,
                "training_recommendations": [],
                "generated_by": "fallback"
            }


if __name__ == "__main__":
    from predict import predict_competencies

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