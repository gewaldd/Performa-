"""
Performa ML Configuration
Location: ml/config.py
"""

import os

# Manuscript 5 Core Competency Categories
COMPETENCY_CATEGORIES = [
    "task_completion",
    "attendance_punctuality",
    "communication_teamwork",
    "initiative_adaptability",
    "quality_of_work"
]

# Target Classes
TARGET_CLASSES = [
    "meets_expectations",
    "needs_improvement",
    "critical_gap"
]

# Score Threshold Boundaries
THRESHOLDS = {
    "meets_expectations": 3.5,
    "needs_improvement": 2.5,
    "critical_gap": 0.0
}

# Paths & Versioning
MODEL_VERSION = "1.0.0"
BASE_DIR = os.path.dirname(__file__)
DATA_PATH = os.path.join(BASE_DIR, "data", "synthetic_kpi_dataset.csv")
MODEL_PATH = os.path.join(BASE_DIR, "models", "random_forest_v1.joblib")

# Gemini API Key loaded securely from environment variables
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY")