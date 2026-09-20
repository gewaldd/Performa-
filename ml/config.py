"""
Performa ML Configuration - Aligned with Final Manuscript (Sept 2026)
Location: ml/config.py

Manuscript refs:
- 5 competencies (P.121), 3 classes per category
- RF 100/8/2 optimal (Table 4), Gini + Permutation (P.502-504)
- Gemini 4 training types (P.121), regularization mid-5th month (P.119)
- Threshold rule aligns with kpi_templates.php kpi_status_for_score() (target-0.8 buffer)
"""

from __future__ import annotations

import os
from typing import Dict, List, Tuple

# --- Competencies (manuscript P.121) ---
COMPETENCY_CATEGORIES: Tuple[str, ...] = (
    "task_completion",
    "attendance_punctuality",
    "communication_teamwork",
    "initiative_adaptability",
    "quality_of_work",
)

COMPETENCY_DISPLAY_NAMES: Dict[str, str] = {
    "task_completion": "Task Completion",
    "attendance_punctuality": "Attendance & Punctuality",
    "communication_teamwork": "Communication & Teamwork",
    "initiative_adaptability": "Initiative & Adaptability",
    "quality_of_work": "Quality of Work",
}

# --- Classes ---
CLASS_MEETS_EXPECTATIONS: str = "meets_expectations"
CLASS_NEEDS_IMPROVEMENT: str = "needs_improvement"
CLASS_CRITICAL_GAP: str = "critical_gap"

CLASSES: Tuple[str, ...] = (
    CLASS_MEETS_EXPECTATIONS,
    CLASS_NEEDS_IMPROVEMENT,
    CLASS_CRITICAL_GAP,
)

# Back-compat alias for older code
TARGET_CLASSES: Tuple[str, ...] = CLASSES

CLASS_DISPLAY_NAMES: Dict[str, str] = {
    CLASS_MEETS_EXPECTATIONS: "Meets Expectations",
    CLASS_NEEDS_IMPROVEMENT: "Needs Improvement",
    CLASS_CRITICAL_GAP: "Critical Gap",
}

CLASS_TO_INDEX: Dict[str, int] = {
    CLASS_MEETS_EXPECTATIONS: 0,
    CLASS_NEEDS_IMPROVEMENT: 1,
    CLASS_CRITICAL_GAP: 2,
}

# --- Thresholds (IMPLEMENTATION ASSUMPTION, documented) ---
# Manuscript does not define numeric cutoffs. We align with
# kpi_templates.php::kpi_status_for_score():
#   score >= target            -> meets
#   target-0.8 <= score < target -> needs
#   score < target-0.8           -> critical
THRESHOLD_BUFFER: float = 0.8
MIN_SCORE: float = 0.0
MAX_SCORE: float = 5.0
DEFAULT_COMPETENCY_TARGET: float = 4.0
RATING_MIN: float = 1.0
RATING_MAX: float = 5.0
RATING_DEFAULT: float = 3.0
MIN_RATINGS_FOR_AGGREGATION: int = 2
DEFAULT_INDUSTRY: str = "retail"


def classify_score(score: float, target: float = DEFAULT_COMPETENCY_TARGET) -> str:
    """Classify one competency score against its target."""
    if not (MIN_SCORE <= score <= MAX_SCORE):
        raise ValueError(f"Score {score} outside [{MIN_SCORE}, {MAX_SCORE}]")
    if not (MIN_SCORE <= target <= MAX_SCORE):
        raise ValueError(f"Target {target} outside [{MIN_SCORE}, {MAX_SCORE}]")
    if score >= target:
        return CLASS_MEETS_EXPECTATIONS
    elif score >= target - THRESHOLD_BUFFER:
        return CLASS_NEEDS_IMPROVEMENT
    else:
        return CLASS_CRITICAL_GAP


def get_weak_categories(classifications: Dict[str, str]) -> List[str]:
    """Return competencies flagged as needs_improvement or critical_gap."""
    return [
        comp for comp, cls in classifications.items()
        if cls in (CLASS_NEEDS_IMPROVEMENT, CLASS_CRITICAL_GAP)
    ]


# --- KPI -> Competency mapping (mirrors kpi_templates.php) ---
KPI_COMPETENCY_MAPPING: Dict[str, Dict[str, List[str]]] = {
    "retail": {
        "sales_target": ["task_completion"],
        "customer_service": ["communication_teamwork", "initiative_adaptability"],
        "inventory_accuracy": ["quality_of_work"],
        "attendance": ["attendance_punctuality"],
    },
    "bpo": {
        "call_quality": ["quality_of_work", "communication_teamwork"],
        "aht": ["task_completion"],
        "customer_satisfaction": ["communication_teamwork"],
        "attendance": ["attendance_punctuality"],
    },
    "food_service": {
        "food_safety": ["quality_of_work"],
        "service_speed": ["task_completion"],
        "customer_service": ["communication_teamwork"],
        "attendance": ["attendance_punctuality"],
    },
    "logistics": {
        "delivery_accuracy": ["quality_of_work"],
        "on_time_rate": ["task_completion", "attendance_punctuality"],
        "safety_compliance": ["quality_of_work"],
        "attendance": ["attendance_punctuality"],
    },
    "construction": {
        "safety_compliance": ["quality_of_work"],
        "work_quality": ["quality_of_work"],
        "productivity": ["task_completion"],
        "attendance": ["attendance_punctuality"],
    },
}

# --- Model (Table 4 Optimized: 100/8/2) ---
MODEL_N_ESTIMATORS: int = 100
MODEL_MAX_DEPTH: int = 8
MODEL_MIN_SAMPLES_SPLIT: int = 2
MODEL_MIN_SAMPLES_LEAF: int = 1
MODEL_RANDOM_STATE: int = 42
MODEL_CLASS_WEIGHT: str = "balanced"
MODEL_CRITERION: str = "gini"
MODEL_VERSION: str = "1.0.0"

MODEL_PARAMS: Dict = {
    "n_estimators": MODEL_N_ESTIMATORS,
    "max_depth": MODEL_MAX_DEPTH,
    "min_samples_split": MODEL_MIN_SAMPLES_SPLIT,
    "min_samples_leaf": MODEL_MIN_SAMPLES_LEAF,
    "criterion": MODEL_CRITERION,
    "random_state": MODEL_RANDOM_STATE,
    "class_weight": MODEL_CLASS_WEIGHT,
}

# --- Paths ---
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_PATH = os.path.join(BASE_DIR, "data", "synthetic_kpi_dataset.csv")
MODEL_PATH = os.path.join(BASE_DIR, "models", "random_forest_v1.joblib")

# Back-compat: old absolute thresholds (deprecated, use classify_score instead)
THRESHOLDS = {
    "meets_expectations": 3.5,
    "needs_improvement": 2.5,
    "critical_gap": 0.0,
}

# --- Training splits (manuscript P.290: 70/15/15) ---
TRAIN_RATIO: float = 0.70
TEST_RATIO: float = 0.15
VAL_RATIO: float = 0.15
RANDOM_SEED: int = 42
TEST_SIZE: float = 0.30  # test+val combined for first split
MIN_TRAINING_SAMPLES: int = 4
MIN_CLASSES_REQUIRED: int = 2
FEATURE_NAMES: List[str] = list(COMPETENCY_CATEGORIES)
NUM_PERMUTATION_ITERATIONS: int = 10

# --- Regularization (manuscript P.119: mid-5th month) ---
REGULARIZATION_MONTH: int = 5
REGULARIZATION_TIMING_DAYS: int = 150

# --- Gemini (manuscript P.121: 4 training types) ---
GEMINI_MODEL: str = "gemini-3.6-flash"
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY")
GEMINI_TRAINING_TYPES: List[str] = [
    "on-the-job coaching",
    "workshop",
    "self-directed learning",
    "mentoring",
]

__all__ = [
    "COMPETENCY_CATEGORIES", "COMPETENCY_DISPLAY_NAMES",
    "CLASSES", "TARGET_CLASSES",
    "CLASS_MEETS_EXPECTATIONS", "CLASS_NEEDS_IMPROVEMENT", "CLASS_CRITICAL_GAP",
    "CLASS_DISPLAY_NAMES", "CLASS_TO_INDEX",
    "THRESHOLD_BUFFER", "MIN_SCORE", "MAX_SCORE", "DEFAULT_COMPETENCY_TARGET",
    "RATING_MIN", "RATING_MAX", "RATING_DEFAULT",
    "classify_score", "get_weak_categories",
    "KPI_COMPETENCY_MAPPING", "MIN_RATINGS_FOR_AGGREGATION", "DEFAULT_INDUSTRY",
    "MODEL_PARAMS", "MODEL_VERSION", "MODEL_PATH", "DATA_PATH", "BASE_DIR",
    "THRESHOLDS",
    "MIN_TRAINING_SAMPLES", "MIN_CLASSES_REQUIRED",
    "FEATURE_NAMES", "NUM_PERMUTATION_ITERATIONS",
    "REGULARIZATION_MONTH", "REGULARIZATION_TIMING_DAYS",
    "GEMINI_MODEL", "GEMINI_API_KEY", "GEMINI_TRAINING_TYPES",
]
