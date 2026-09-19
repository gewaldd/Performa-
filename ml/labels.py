# Performa Label Generation
#
# Generates training labels for the Random Forest based on competency scores
# and threshold rules.
#
# The manuscript requires three target classes per competency category:
#   - meets_expectations
#   - needs_improvement
#   - critical_gap
#
# This module applies threshold-based labeling rules. These thresholds are
# IMPLEMENTATION ASSUMPTIONS and must be reviewed/approved by the project team.
#
# Labeling is done PER COMPETENCY CATEGORY, not per employee overall.
# Each (employee, month, competency) combination produces one training label.

from __future__ import annotations

from typing import Dict, List, Tuple

from config import (
    CLASSES,
    COMPETENCY_CATEGORIES,
    MIN_CLASSES_REQUIRED,
    MIN_TRAINING_SAMPLES,
    classify_score,
)

from features import CompetencyScores, FEATURE_NAMES


def make_training_record(
    employee_id: str,
    evaluation_month: str,
    competency_category: str,
    competency_score: float,
    competency_target: float,
) -> dict:
    """Create a single training record for one competency classification."""
    return {
        "employee_id": employee_id,
        "evaluation_month": evaluation_month,
        "competency_category": competency_category,
        "task_completion": competency_score if competency_category == "task_completion" else None,
        "attendance_punctuality": competency_score if competency_category == "attendance_punctuality" else None,
        "communication_teamwork": competency_score if competency_category == "communication_teamwork" else None,
        "initiative_adaptability": competency_score if competency_category == "initiative_adaptability" else None,
        "quality_of_work": competency_score if competency_category == "quality_of_work" else None,
        "target_class": classify_score(competency_score, competency_target),
    }


def make_training_records_from_scores(
    employee_id: str,
    evaluation_month: str,
    competency_scores: CompetencyScores,
    competency_targets: Dict[str, float],
) -> List[dict]:
    """Create training records for ALL competency categories for one employee/month."""
    records: List[dict] = []
    for competency in COMPETENCY_CATEGORIES:
        score = competency_scores.get(competency)
        target = competency_targets.get(competency)
        if score is None or target is None:
            continue
        records.append(make_training_record(
            employee_id=employee_id,
            evaluation_month=evaluation_month,
            competency_category=competency,
            competency_score=score,
            competency_target=target,
        ))
    return records


def build_training_dataset(
    competency_data: Dict[Tuple[str, str], CompetencyScores],
    competency_targets: Dict[str, float],
) -> List[dict]:
    """Build a complete training dataset from aggregated competency data."""
    dataset: List[dict] = []
    for (employee_id, month), scores in competency_data.items():
        records = make_training_records_from_scores(
            employee_id=employee_id,
            evaluation_month=month,
            competency_scores=scores,
            competency_targets=competency_targets,
        )
        dataset.extend(records)
    return dataset


def validate_dataset(dataset: List[dict]) -> List[str]:
    """Validate a training dataset and return a list of issues found."""
    issues: List[str] = []
    
    if len(dataset) < MIN_TRAINING_SAMPLES:
        issues.append(f"Insufficient training samples: {len(dataset)} (minimum: {MIN_TRAINING_SAMPLES})")
    
    class_counts: Dict[str, int] = {c: 0 for c in CLASSES}
    for record in dataset:
        label = record.get("target_class")
        if label in class_counts:
            class_counts[label] += 1
    
    present_classes = [c for c, count in class_counts.items() if count > 0]
    if len(present_classes) < MIN_CLASSES_REQUIRED:
        issues.append(f"Insufficient class diversity: {len(present_classes)} class(es) present")
    
    for i, record in enumerate(dataset):
        for feature in COMPETENCY_CATEGORIES:
            value = record.get(feature)
            if value is None:
                issues.append(f"Record {i}: Missing feature value for {feature}")
            elif not (0.0 <= value <= 5.0):
                issues.append(f"Record {i}: Invalid feature value for {feature}: {value}")
        label = record.get("target_class")
        if label not in CLASSES:
            issues.append(f"Record {i}: Invalid target class '{label}'")
    
    return issues


def get_class_distribution(dataset: List[dict]) -> Dict[str, int]:
    """Get the class distribution of a training dataset."""
    distribution: Dict[str, int] = {c: 0 for c in CLASSES}
    for record in dataset:
        label = record.get("target_class")
        if label in distribution:
            distribution[label] += 1
    return distribution


def extract_features(dataset: List[dict]) -> List[List[float]]:
    """Extract the feature matrix from a list of training records."""
    features: List[List[float]] = []
    for record in dataset:
        vector = [float(record.get(f, 0.0)) for f in FEATURE_NAMES]
        features.append(vector)
    return features


def extract_targets(dataset: List[dict]) -> List[str]:
    """Extract the target labels from a list of training records."""
    return [str(record.get("target_class", "")) for record in dataset]


__all__ = [
    "make_training_record",
    "make_training_records_from_scores",
    "build_training_dataset",
    "validate_dataset",
    "get_class_distribution",
    "extract_features",
    "extract_targets",
]

