#!/usr/bin/env python3
"""Setup script for Performa ML modules."""
from pathlib import Path

ML_DIR = Path(r"D:\Downloads\odysseus-dev\Performa-\ml")

config_py = """# Performa ML Configuration
# Defines constants aligning Random Forest with the capstone manuscript.

from __future__ import annotations
from typing import Dict, List, Tuple


# COMPETENCY CATEGORIES (manuscript line 137)
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

# TARGET CLASSES (manuscript lines 137, 480)
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

# CLASSIFICATION THRESHOLDS (IMPLEMENTATION ASSUMPTION)
# OPEN DECISION: Manuscript doesn't define exact thresholds.
# Based on kpi_status_for_score() in kpi_templates.php (0.8 buffer).
# Rule: score >= target -> meets; target-0.8 <= score < target -> needs_improvement;
#       score < target-0.8 -> critical_gap

THRESHOLD_BUFFER: float = 0.8
MIN_SCORE: float = 0.0
MAX_SCORE: float = 5.0


def classify_score(score: float, target: float) -> str:
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


# KPI-TO-COMPETENCY MAPPING (IMPLEMENTATION ASSUMPTION)
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

MIN_RATINGS_FOR_AGGREGATION: int = 2

MODEL_N_ESTIMATORS: int = 300
MODEL_RANDOM_STATE: int = 42
MODEL_CLASS_WEIGHT: str = "balanced"
MODEL_MIN_SAMPLES_LEAF: int = 2

MODEL_PARAMS: Dict = {
    "n_estimators": MODEL_N_ESTIMATORS,
    "random_state": MODEL_RANDOM_STATE,
    "class_weight": MODEL_CLASS_WEIGHT,
    "min_samples_leaf": MODEL_MIN_SAMPLES_LEAF,
}

MIN_TRAINING_SAMPLES: int = 4
MIN_CLASSES_REQUIRED: int = 2

__all__ = [
    "COMPETENCY_CATEGORIES", "COMPETENCY_DISPLAY_NAMES", "CLASSES",
    "CLASS_MEETS_EXPECTATIONS", "CLASS_NEEDS_IMPROVEMENT", "CLASS_CRITICAL_GAP",
    "CLASS_DISPLAY_NAMES", "THRESHOLD_BUFFER", "MIN_SCORE", "MAX_SCORE",
    "classify_score", "KPI_COMPETENCY_MAPPING", "MIN_RATINGS_FOR_AGGREGATION",
    "MODEL_PARAMS", "MIN_TRAINING_SAMPLES", "MIN_CLASSES_REQUIRED",
]
"""

(ML_DIR / "config.py").write_text(config_py)
print("Created config.py")
