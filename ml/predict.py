"""
Performa ML Engine - Competency Classification Prediction
Location: ml/predict.py

Manuscript alignment (Final Manuscript Sept 2026, P.121 + P.272):
- Input: monthly aggregated competency scores (5 categories).
- RF predicts OVERALL month class + calibrated probability via predict_proba.
- Per-competency gaps via classify_score(score, target) with target-0.8 buffer
  (aligns with kpi_templates.php::kpi_status_for_score).
- weak_categories = per-competency needs/critical -> passed to Gemini.
- Gini + permutation importances returned for Article 296 audit trail.
- Returns overall_prediction + per-competency breakdown (no hardcoded probs).
"""

from __future__ import annotations

from datetime import datetime, timezone
import os
import sys
from typing import Dict, Optional

# Make `from config import ...` + `from ml.config import ...` work regardless
# of cwd (CLI root, ml/ dir, or Apache proc_open with cwd=ml/).
_HERE = os.path.dirname(os.path.abspath(__file__))
_ROOT = os.path.dirname(_HERE)
for _p in (_HERE, _ROOT):
    if _p not in sys.path:
        sys.path.insert(0, _p)

try:
    from config import (
        COMPETENCY_CATEGORIES,
        DEFAULT_COMPETENCY_TARGET,
        MODEL_PATH,
        MODEL_VERSION,
        RATING_DEFAULT,
        classify_score,
        get_weak_categories,
    )
except ImportError:
    from ml.config import (
        COMPETENCY_CATEGORIES,
        DEFAULT_COMPETENCY_TARGET,
        MODEL_PATH,
        MODEL_VERSION,
        RATING_DEFAULT,
        classify_score,
        get_weak_categories,
    )

# Industry KPI key -> canonical competency (covers all 5 templates + canonical names)
KEY_ALIASES: Dict[str, str] = {
    # canonical passthrough
    "task_completion": "task_completion",
    "attendance_punctuality": "attendance_punctuality",
    "communication_teamwork": "communication_teamwork",
    "initiative_adaptability": "initiative_adaptability",
    "quality_of_work": "quality_of_work",
    # retail
    "sales_target": "task_completion",
    "inventory_accuracy": "quality_of_work",
    # bpo
    "call_quality": "quality_of_work",
    "aht": "task_completion",
    "customer_satisfaction": "communication_teamwork",
    # food_service
    "food_safety": "quality_of_work",
    "service_speed": "task_completion",
    # logistics
    "delivery_accuracy": "quality_of_work",
    "on_time_rate": "task_completion",
    "safety_compliance": "quality_of_work",
    # construction
    "work_quality": "quality_of_work",
    "productivity": "task_completion",
    # shared
    "attendance": "attendance_punctuality",
    "customer_service": "communication_teamwork",
}


def _normalize_scores(category_scores: dict) -> Dict[str, float]:
    normalized: Dict[str, float] = {}
    for raw_key, val in (category_scores or {}).items():
        std_key = KEY_ALIASES.get(str(raw_key), str(raw_key))
        if std_key not in COMPETENCY_CATEGORIES:
            continue
        try:
            normalized[std_key] = float(val)
        except (ValueError, TypeError):
            normalized[std_key] = float(RATING_DEFAULT)
    # Fill missing competencies with default so the RF always sees 5 features.
    # Missing flags are reported to the caller for transparency.
    for cat in COMPETENCY_CATEGORIES:
        if cat not in normalized:
            normalized[cat] = float(RATING_DEFAULT)
    return normalized


def _load_bundle(model_path: Optional[str] = None) -> dict:
    # Lazy imports so the module stays importable under Apache bare runtimes;
    # missing deps raise a clear error caught by the STDIN handler.
    try:
        import joblib as _joblib
    except ImportError as e:
        raise ImportError(
            "joblib is not installed for this Python interpreter. "
            "Install ml/requirements.txt or set PYTHON_EXEC to the project Python."
        ) from e
    path = model_path or MODEL_PATH
    if not os.path.exists(path):
        raise FileNotFoundError(
            f"Model bundle not found at {path}. Run ml/train.py first."
        )
    bundle = _joblib.load(path)
    if "model" not in bundle:
        raise ValueError(f"Invalid model bundle at {path}: missing 'model' key")
    return bundle


def _threshold_fallback(normalized: Dict[str, float], targets: Dict[str, float],
                        employee_uid: str, evaluation_month: str,
                        missing: list, reason: str) -> dict:
    """Honest threshold-only path when RF deps/model are unavailable.

    Manuscript prefers RF; this keeps the web app functional (rating still saves)
    while marking provenance so the audit trail is not misleading.
    """
    try:
        from labels import overall_label_from_per_category, per_category_labels
    except ImportError:
        from ml.labels import overall_label_from_per_category, per_category_labels
    per_labels = per_category_labels(normalized, targets)
    overall = overall_label_from_per_category(per_labels)
    per_comp: Dict[str, dict] = {}
    for cat in COMPETENCY_CATEGORIES:
        score = float(normalized[cat])
        per_comp[cat] = {
            "classification": per_labels.get(cat, classify_score(score, float(targets[cat]))),
            "score": round(score, 2),
            "target": round(float(targets[cat]), 2),
            "gap": round(float(targets[cat]) - score, 2),
            "feature_importance": {"gini": 0.0, "permutation": 0.0},
        }
    return {
        "employee_uid": employee_uid,
        "evaluation_month": evaluation_month,
        "raw_scores": {},
        "normalized_scores": {k: round(float(v), 2) for k, v in normalized.items()},
        "missing_competencies_defaulted": missing,
        "competency_targets": {k: round(float(v), 2) for k, v in targets.items()},
        "overall_prediction": {
            "classification": overall,
            "confidence": 0.0,
            "class_probabilities": {},
            "inference": "threshold-fallback",
            "fallback_reason": reason,
        },
        "competency_classification": per_comp,
        "weak_categories": get_weak_categories(per_labels),
        "model_version": MODEL_VERSION + "+threshold-fallback",
        "model_params": {},
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "disclaimer": "Decision-support output only; RF unavailable in this runtime, threshold fallback used.",
    }


def predict_competencies(
    employee_uid: str,
    evaluation_month: str,
    category_scores: dict,
    competency_targets: Dict[str, float] | None = None,
    model_path: Optional[str] = None,
) -> dict:
    """Run real RF inference + per-competency threshold labeling.

    :param competency_targets: per-competency targets (default 4.0 each).
    :returns: dict with overall_prediction, per-competency classifications,
              weak_categories, importances, model_version for audit.
    """
    targets = dict(competency_targets or {})
    for cat in COMPETENCY_CATEGORIES:
        targets.setdefault(cat, DEFAULT_COMPETENCY_TARGET)

    normalized = _normalize_scores(category_scores)
    missing = [c for c in COMPETENCY_CATEGORIES if c not in (category_scores or {}) and c not in
               {KEY_ALIASES.get(str(k), str(k)) for k in (category_scores or {}).keys()}]

    try:
        bundle = _load_bundle(model_path)
    except (ImportError, FileNotFoundError, ValueError) as e:
        fb = _threshold_fallback(normalized, targets, employee_uid, evaluation_month, missing, str(e))
        fb["raw_scores"] = dict(category_scores or {})
        return fb
    try:
        import pandas as _pd
    except ImportError as e:
        fb = _threshold_fallback(normalized, targets, employee_uid, evaluation_month, missing,
                                 "pandas is not installed for this Python interpreter. " + str(e))
        fb["raw_scores"] = dict(category_scores or {})
        return fb
    rf_model = bundle["model"]
    feature_names = bundle.get("feature_names", list(COMPETENCY_CATEGORIES))
    gini = bundle.get("feature_importances", {})
    perm = bundle.get("permutation_importances", {})

    X_input = _pd.DataFrame([{c: float(normalized[c]) for c in feature_names}])

    # Real model inference (no hardcoded probabilities)
    overall_class: str = str(rf_model.predict(X_input)[0])
    proba: Dict[str, float] = {}
    if hasattr(rf_model, "predict_proba"):
        try:
            probs = [float(v) for v in rf_model.predict_proba(X_input)[0]]
            proba = {str(cls): round(p, 4) for cls, p in zip(rf_model.classes_, probs)}
        except Exception:
            proba = {}
    overall_conf = round(float(proba.get(overall_class, 0.0)), 4) if proba else 0.0

    per_comp: Dict[str, dict] = {}
    per_labels: Dict[str, str] = {}
    for cat in COMPETENCY_CATEGORIES:
        score = float(normalized[cat])
        label = classify_score(score, float(targets[cat]))
        per_labels[cat] = label
        per_comp[cat] = {
            "classification": label,
            "score": round(score, 2),
            "target": round(float(targets[cat]), 2),
            "gap": round(float(targets[cat]) - score, 2),
            "feature_importance": {
                "gini": round(float(gini.get(cat, 0.0)), 4),
                "permutation": round(float(perm.get(cat, 0.0)), 4),
            },
        }

    weak = get_weak_categories(per_labels)

    return {
        "employee_uid": employee_uid,
        "evaluation_month": evaluation_month,
        "raw_scores": dict(category_scores or {}),
        "normalized_scores": {k: round(float(v), 2) for k, v in normalized.items()},
        "missing_competencies_defaulted": missing,
        "competency_targets": {k: round(float(v), 2) for k, v in targets.items()},
        "overall_prediction": {
            "classification": overall_class,
            "confidence": overall_conf,
            "class_probabilities": proba,
        },
        "competency_classification": per_comp,
        "weak_categories": weak,
        "model_version": bundle.get("version", MODEL_VERSION),
        "model_params": bundle.get("params", {}),
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "disclaimer": "Decision-support output only; final employment decisions remain with the employer.",
    }


if __name__ == "__main__":
    import json

    demo = {
        "food_safety": 3.0,
        "service_speed": 2.1,
        "customer_service": 3.0,
        "attendance": 3.0,
    }
    print(json.dumps(predict_competencies("EMP-1002", "2026-09", demo), indent=2))
