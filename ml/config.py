#!/usr/bin/env python3
"""
Performa ML Configuration - Aligned with Capstone Manuscript

Manuscript References:
- Lines 137, 276-278: Five core KPI competency categories
- Lines 480, 489: Three-category classification
- Lines 495-504: Random Forest with Gini Impurity Feature Importance
- Lines 528-529: Gini + Permutation Feature Importance
- Lines 137, 158: Gemini API integration for training recommendations
- Lines 543: Random Forest model training
- Line 561: Regularization at 5th month
- Line 540: Threshold-based labeling
"""

from typing import List, Dict, Any, Optional, Tuple

# Five Core KPI Competency Categories (Lines 137, 276-278)
COMPETENCY_CATEGORIES: Tuple[str, ...] = (
    "task_completion",
    "attendance_punctuality",
    "communication_teamwork",
    "initiative_adaptability",
    "quality_of_work",
)

COMPETENCY_DISPLAY_NAMES: Dict[str, str] = {
    "task_completion": "Task Completion",
    "attendance_punctuality": "Attendance and Punctuality",
    "communication_teamwork": "Communication and Teamwork",
    "initiative_adaptability": "Initiative and Adaptability",
    "quality_of_work": "Quality of Work",
}

# Classification Threshold Constants (Line 540)
THRESHOLD_BUFFER: float = 0.8
MIN_SCORE: float = 0.0
MAX_SCORE: float = 5.0
DEFAULT_COMPETENCY_TARGET: float = 4.0
RATING_MIN: float = 1.0
RATING_MAX: float = 5.0
RATING_DEFAULT: float = 3.0
MIN_RATINGS_FOR_AGGREGATION: int = 2
DEFAULT_INDUSTRY: str = "retail"


# Three-Category Classification (Lines 480, 489)
CLASS_MEETS_EXPECTATIONS: str = "meets_expectations"
CLASS_NEEDS_IMPROVEMENT: str = "needs_improvement"
CLASS_CRITICAL_GAP: str = "critical_gap"

CLASSES: Tuple[str, ...] = (
    CLASS_MEETS_EXPECTATIONS,
    CLASS_NEEDS_IMPROVEMENT,
    CLASS_CRITICAL_GAP,
)

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


def classify_score(score: float, target: float = DEFAULT_COMPETENCY_TARGET) -> str:
    """Classify a competency score into one of three categories.

    Manuscript Line 540: threshold-based labeling using mean scores.
    - score >= target -> meets_expectations
    - target-0.8 <= score < target -> needs_improvement
    - score < target-0.8 -> critical_gap
    """
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
    """Return competency categories that need improvement or have critical gaps."""
    return [
        comp for comp, cls in classifications.items()
        if cls in (CLASS_NEEDS_IMPROVEMENT, CLASS_CRITICAL_GAP)
    ]

# KPI-to-Competency MAPPING (kpi_templates.php)
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


# Random Forest Model Configuration (Lines 495-504, 516)
MODEL_N_ESTIMATORS: int = 300
MODEL_MAX_DEPTH: Optional[int] = None
MODEL_MIN_SAMPLES_SPLIT: int = 2
MODEL_MIN_SAMPLES_LEAF: int = 2
MODEL_RANDOM_STATE: int = 42
MODEL_CLASS_WEIGHT: str = "balanced"
MODEL_N_JOBS: int = -1
MODEL_VERSION: str = "1.0.0"
MODEL_PATH: str = "ml/models/random_forest_model.pkl"

MODEL_PARAMS: Dict[str, Any] = {
    "n_estimators": MODEL_N_ESTIMATORS,
    "max_depth": MODEL_MAX_DEPTH,
    "min_samples_split": MODEL_MIN_SAMPLES_SPLIT,
    "min_samples_leaf": MODEL_MIN_SAMPLES_LEAF,
    "random_state": MODEL_RANDOM_STATE,
    "class_weight": MODEL_CLASS_WEIGHT,
    "n_jobs": MODEL_N_JOBS,
}

# Training Configuration
MIN_TRAINING_SAMPLES: int = 4
MIN_CLASSES_REQUIRED: int = 2
TEST_SIZE: float = 0.2
RANDOM_SEED: int = 42
FEATURE_NAMES: List[str] = list(COMPETENCY_CATEGORIES)
FEATURE_IMPORTANCE_METHOD: str = "gini"
NUM_PERMUTATION_ITERATIONS: int = 10

# Regularization Configuration (Line 561: 5th month)
REGULARIZATION_MONTH: int = 5
REGULARIZATION_TIMING_DAYS: int = 150

# Gemini Integration (Lines 137, 158, 200, 272)
GEMINI_MODEL: str = "gemini-2.5-flash"
GEMINI_API_KEY_ENV_VAR: str = "GEMINI_API_KEY"
GEMINI_MAX_OUTPUT_TOKENS: int = 2048
GEMINI_TRAINING_TYPES: List[str] = [
    "on-the-job coaching",
    "workshop",
    "self-directed learning",
    "mentoring",
]
GEMINI_TIMELINE_OPTIONS_WEEKS: List[int] = [2, 4, 6, 8]

__all__ = [
    "COMPETENCY_CATEGORIES", "COMPETENCY_DISPLAY_NAMES", "CLASSES",
    "CLASS_MEETS_EXPECTATIONS", "CLASS_NEEDS_IMPROVEMENT", "CLASS_CRITICAL_GAP",
    "CLASS_DISPLAY_NAMES", "CLASS_TO_INDEX", "THRESHOLD_BUFFER",
    "MIN_SCORE", "MAX_SCORE", "DEFAULT_COMPETENCY_TARGET", "classify_score",
    "get_weak_categories",
    "RATING_MIN", "RATING_MAX", "RATING_DEFAULT",
    "MIN_RATINGS_FOR_AGGREGATION", "DEFAULT_INDUSTRY",
    "KPI_COMPETENCY_MAPPING",
    "MODEL_PARAMS", "MODEL_VERSION", "MODEL_PATH",
    "MIN_TRAINING_SAMPLES", "MIN_CLASSES_REQUIRED",
    "RANDOM_SEED", "TEST_SIZE", "FEATURE_NAMES",
    "FEATURE_IMPORTANCE_METHOD", "NUM_PERMUTATION_ITERATIONS",
    "REGULARIZATION_MONTH", "REGULARIZATION_TIMING_DAYS",
    "GEMINI_MODEL", "GEMINI_API_KEY_ENV_VAR", "GEMINI_MAX_OUTPUT_TOKENS",
    "GEMINI_TRAINING_TYPES", "GEMINI_TIMELINE_OPTIONS_WEEKS",
]
