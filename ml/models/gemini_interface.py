"""
Performa Gemini API Integration
Manuscript Constraint: Gemini accepts Random Forest classifications as ground truth
and generates personalized natural language training recommendations.
"""

import json
import os
import google.generativeai as genai

GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
if GEMINI_API_KEY:
    genai.configure(api_key=GEMINI_API_KEY)

def generate_training_recommendations(rf_result: dict, job_role: str, industry: str) -> dict:
    if not GEMINI_API_KEY:
        raise ValueError("GEMINI_API_KEY environment variable is not configured.")

    model = genai.GenerativeModel("gemini-1.5-flash")

    prompt = f"""
    You are an expert HR Training & Development Specialist.
    Analyze the Random Forest KPI classification results for a probationary employee and generate targeted training recommendations.

    CONTEXT:
    - Industry: {industry}
    - Job Role: {job_role}
    - Identified Weak Categories (by RF): {json.dumps(rf_result['weak_categories'])}
    - Full RF Classification: {json.dumps(rf_result['competency_classification'])}

    CONSTRAINTS:
    1. DO NOT re-classify or override any Random Forest outputs. Accept them as ground truth.
    2. Provide action-oriented recommendations ONLY for categories flagged as 'needs_improvement' or 'critical_gap'.
    3. Return strictly valid JSON with no extra commentary.

    REQUIRED JSON FORMAT:
    {{
      "training_recommendations": [
        {{
          "competency_area": "quality_of_work",
          "training_type": "on-the-job coaching|workshop|self-directed learning|mentoring",
          "description": "Clear step-by-step recommendation",
          "timeline": "2-4 weeks",
          "rationale": "Justification based on recorded performance gap"
        }}
      ],
      "summary": "Synthesized evaluation summary."
    }}
    """

    response = model.generate_content(
        prompt,
        generation_config={"response_mime_type": "application/json"}
    )

    output = json.loads(response.text)
    output["generated_by"] = "gemini_api"
    return output