# Performa Label Generation
#
# Generates training labels for the Random Forest based on competency scores
# and threshold rules.
#
# Manuscript requires three target classes per competency category:
#   - meets_expectations / needs_improvement / critical_gap
#
# IMPLEMENTATION (documented assumption, aligns with kpi_templates.php):
#   Per-competency label via classify_score(score, target) with target-0.8 buffer.
#   Overall employee-month label for RF training via overall_label_from_per_category():
#     >=2 critical OR (1 critical + >=2 needs) -> critical_gap
#     >=2 needs (or 1 critical alone)          -> needs_improvement
#     else                                    -> meets_expectations
#   Each (employee, month) yields ONE dense row with all 5 scores + overall label.
#   Per-competency records are kept for audit/Gemini weak-category detection.

from __future__ import annotations

from typing import Dict, List, Tuple

try:
    from config import (
        CLASSES,
        CLASS_CRITICAL_GAP,
        CLASS_MEETS_EXPECTATIONS,
        CLASS_NEEDS_IMPROVEMENT,
        COMPETENCY_CATEGORIES,
        MIN_CLASSES_REQUIRED,
        MIN_TRAINING_SAMPLES,
        classify_score,
    )
except ImportError:
    from ml.config import (
        CLASSES,
        CLASS_CRITICAL_GAP,
        CLASS_MEETS_EXPECTATIONS,
        CLASS_NEEDS_IMPROVEMENT,
        COMPETENCY_CATEGORIES,
        MIN_CLASSES_REQUIRED,
        MIN_TRAINING_SAMPLES,
        classify_score,
    )

try:
    from features import CompetencyScores, FEATURE_NAMES
except ImportError:
    from ml.features import CompetencyScores, FEATURE_NAMES


def per_category_labels(
    competency_scores: CompetencyScores,
    competency_targets: Dict[str, float],
) -> Dict[str, str]:
    """Label each competency via classify_score(). Skips missing scores/targets."""
    out: Dict[str, str] = {}
    for comp in COMPETENCY_CATEGORIES:
        score = competency_scores.get(comp)
        target = competency_targets.get(comp)
        if score is None or target is None:
            continue
        out[comp] = classify_score(float(score), float(target))
    return out


def overall_label_from_per_category(per_cat: Dict[str, str]) -> str:
    """Derive overall month label from per-category labels (documented rule)."""
    labels = list(per_cat.values())
    n_crit = sum(1 for v in labels if v == CLASS_CRITICAL_GAP)
    n_needs = sum(1 for v in labels if v == CLASS_NEEDS_IMPROVEMENT)
    if n_crit >= 2 or (n_crit >= 1 and n_needs >= 2):
        return CLASS_CRITICAL_GAP
    if n_crit >= 1 or n_needs >= 2:
        return CLASS_NEEDS_IMPROVEMENT
    return CLASS_MEETS_EXPECTATIONS


def make_training_record(
    employee_id: str,
    evaluation_month: str,
    competency_category: str,
    competency_score: float,
    competency_target: float,
    full_scores: CompetencyScores | None = None,
) -> dict:
    """Create one audit record for a single competency.

    When full_scores is provided, the record carries the dense 5-score
    context (preferred for RF training). Otherwise falls back to legacy
    sparse layout for backward compatibility.
    """
    label = classify_score(competency_score, competency_target)
    if full_scores is not None:
        row = {c: float(full_scores.get(c)) for c in COMPETENCY_CATEGORIES}
    else:
        row = {
            c: (float(competency_score) if c == competency_category else None)
            for c in COMPETENCY_CATEGORIES
        }
    row.update({
        "employee_id": employee_id,
        "evaluation_month": evaluation_month,
        "competency_category": competency_category,
        "target_class": label,
    })
    return row


def make_overall_training_row(
    employee_id: str,
    evaluation_month: str,
    competency_scores: CompetencyScores,
    competency_targets: Dict[str, float],
) -> dict | None:
    """Create ONE dense training row for an employee-month. Returns None if incomplete."""
    for comp in COMPETENCY_CATEGORIES:
        if competency_scores.get(comp) is None or competency_targets.get(comp) is None:
            return None
    per_cat = per_category_labels(competency_scores, competency_targets)
    if len(per_cat) != len(COMPETENCY_CATEGORIES):
        return None
    row = {c: float(competency_scores[c]) for c in COMPETENCY_CATEGORIES}
    row.update({
        "employee_id": employee_id,
        "evaluation_month": evaluation_month,
        "target_classification": overall_label_from_per_category(per_cat),
        "per_category": per_cat,
    })
    return row


def make_training_records_from_scores(
    employee_id: str,
    evaluation_month: str,
    competency_scores: CompetencyScores,
    competency_targets: Dict[str, float],
) -> List[dict]:
    """Create audit records for ALL competencies (dense context included)."""
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
            competency_score=float(score),
            competency_target=float(target),
            full_scores={c: float(competency_scores[c]) for c in COMPETENCY_CATEGORIES if competency_scores.get(c) is not None},
        ))
    return records


def build_training_dataset(
    competency_data: Dict[Tuple[str, str], CompetencyScores],
    competency_targets: Dict[str, float],
) -> List[dict]:
    """Build dense overall-label dataset: one row per (employee, month)."""
    dataset: List[dict] = []
    for (employee_id, month), scores in competency_data.items():
        row = make_overall_training_row(
            employee_id=employee_id,
            evaluation_month=month,
            competency_scores=scores,
            competency_targets=competency_targets,
        )
        if row is not None:
            dataset.append(row)
    return dataset


def validate_dataset(dataset: List[dict]) -> List[str]:
    """Validate a dense training dataset."""
    issues: List[str] = []
    if len(dataset) < MIN_TRAINING_SAMPLES:
        issues.append(f"Insufficient training samples: {len(dataset)} (minimum: {MIN_TRAINING_SAMPLES})")
    class_counts: Dict[str, int] = {c: 0 for c in CLASSES}
    label_key_candidates = ("target_classification", "target_class")
    for record in dataset:
        label = next((record.get(k) for k in label_key_candidates if record.get(k) in class_counts), None)
        if label in class_counts:
            class_counts[label] += 1
    present = [c for c, n in class_counts.items() if n > 0]
    if len(present) < MIN_CLASSES_REQUIRED:
        issues.append(f"Insufficient class diversity: {len(present)} class(es) present")
    for i, record in enumerate(dataset):
        for feature in COMPETENCY_CATEGORIES:
            value = record.get(feature)
            if value is None:
                issues.append(f"Record {i}: Missing feature value for {feature}")
            else:
                try:
                    v = float(value)
                except (TypeError, ValueError):
                    issues.append(f"Record {i}: Non-numeric feature value for {feature}: {value}")
                    continue
                if not (0.0 <= v <= 5.0):
                    issues.append(f"Record {i}: Invalid feature value for {feature}: {value}")
        label = next((record.get(k) for k in label_key_candidates), None)
        if label not in CLASSES:
            issues.append(f"Record {i}: Invalid target class '{label}'")
    return issues


def get_class_distribution(dataset: List[dict]) -> Dict[str, int]:
    """Get class distribution (supports both label key names)."""
    distribution: Dict[str, int] = {c: 0 for c in CLASSES}
    for record in dataset:
        label = record.get("target_classification", record.get("target_class"))
        if label in distribution:
            distribution[label] += 1
    return distribution


def extract_features(dataset: List[dict]) -> List[List[float]]:
    """Extract dense feature matrix."""
    features: List[List[float]] = []
    for record in dataset:
        vector = [float(record.get(f, 0.0)) for f in FEATURE_NAMES]
        features.append(vector)
    return features


def extract_targets(dataset: List[dict]) -> List[str]:
    """Extract target labels (supports both key names)."""
    return [str(record.get("target_classification", record.get("target_class", ""))) for record in dataset]


__all__ = [
    "per_category_labels",
    "overall_label_from_per_category",
    "make_training_record",
    "make_overall_training_row",
    "make_training_records_from_scores",
    "build_training_dataset",
    "validate_dataset",
    "get_class_distribution",
    "extract_features",
    "extract_targets",
]
