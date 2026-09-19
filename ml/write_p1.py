#!/usr/bin/env python3
"""Write part 1 of predict.py"""

p1 = '''#!/usr/bin/env python3
"""
Performa Random Forest Prediction Module

Predicts competency gap classifications for probationary employees.

ALIGNS WITH MANUSCRIPT (Lines 137, 276-278, 480, 489, 497-498):
- Five core KPI competency categories
- Three-class classification
- RF classification feeds Gemini training recommendations
"""

import json
import pickle
import sys
from pathlib import Path
from typing import Any, Dict, List, Optional

from config import (
    CLASSES,
    CLASS_MEETS_EXPECTATIONS,
    CLASS_NEEDS_IMPROVEMENT,
    CLASS_CRITICAL_GAP,
    CLASS_DISPLAY_NAMES,
    COMPETENCY_CATEGORIES,
    COMPETENCY_DISPLAY_NAMES,
    FEATURE_NAMES,
    MODEL_PARAMS,
    MODEL_VERSION,
    DEFAULT_COMPETENCY_TARGET,
    classify_score,
)
'''

with open("ml/predict.py", "w") as f:
    f.write(p1)
print("Part 1 written")

p2 = '''

def build_feature_vector(
    employee: Dict[str, Any],
    competency_targets: Optional[Dict[str, float]] = None,
) -> List[float]:
    """Extract feature vector from an employee dict.

    Args:
        employee: Dict with competency scores keyed by category name
        competency_targets: Optional target scores (unused for features)

    Returns:
        List of 5 floats in FEATURE_NAMES order

    Raises:
        ValueError: If a required feature is missing
    """
    features: List[float] = []
    uid = employee.get("uid", "unknown")
    for fname in FEATURE_NAMES:
        val = employee.get(fname)
        if val is None:
            raise ValueError(f"Missing feature '{fname}' for employee {uid}")
        features.append(float(val))
    return features


def classify_competencies(
    features: List[float],
    competency_targets: Optional[Dict[str, float]] = None,
) -> Dict[str, Dict[str, Any]]:
    """Classify each competency category using threshold-based rules.

    Uses the classify_score function from config to classify each of the
    five competency scores.
    """
    if competency_targets is None:
        competency_targets = {}
    results: Dict[str, Dict[str, Any]] = {}
    for i, competency in enumerate(COMPETENCY_CATEGORIES):
        score = features[i]
        target = competency_targets.get(
            competency, DEFAULT_COMPETENCY_TARGET
        )
        classification = classify_score(score, target)
        results[competency] = {
            "classification": classification,
            "display_name": COMPETENCY_DISPLAY_NAMES.get(
                competency, competency
            ),
            "score": round(score, 2),
            "target": round(target, 2),
            "meets_expectations": classification == CLASS_MEETS_EXPECTATIONS,
            "needs_improvement": classification == CLASS_NEEDS_IMPROVEMENT,
            "critical_gap": classification == CLASS_CRITICAL_GAP,
        }
    return results
'''

with open("ml/predict.py", "a") as f:
    f.write(p2)
print("Part 2 written")

p3 = '''

def predict_employee(
    model: Any,
    employee: Dict[str, Any],
    competency_targets: Optional[Dict[str, float]] = None,
) -> Dict[str, Any]:
    """Predict competency classifications for one employee.

    Combines RF model prediction with threshold-based per-competency scoring.
    """
    uid = employee.get("uid", "unknown")
    features = build_feature_vector(employee, competency_targets)

    overall_prediction = None
    overall_confidence = None
    model_version = MODEL_VERSION

    if model is not None:
        proba = model.predict_proba([features])[0]
        overall_prediction = model.predict([features])[0]
        overall_confidence = float(max(proba))

    results = classify_competencies(features, competency_targets)

    weak_categories: List[str] = []
    for competency in COMPETENCY_CATEGORIES:
        cls = results[competency]["classification"]
        if cls in (CLASS_NEEDS_IMPROVEMENT, CLASS_CRITICAL_GAP):
            weak_categories.append(competency)

    return {
        "uid": uid,
        "competency_classification": results,
        "weak_categories": weak_categories,
        "overall_prediction": overall_prediction,
        "overall_confidence": round(overall_confidence, 4)
        if overall_confidence is not None else None,
        "model": "random_forest",
        "model_version": model_version,
    }
'''

with open("ml/predict.py", "a") as f:
    f.write(p3)
print("Part 3 written")

p4 = '''

def predict_all(
    model: Any,
    employees: List[Dict[str, Any]],
    competency_targets: Optional[Dict[str, float]] = None,
) -> Dict[str, Dict[str, Any]]:
    """Predict classifications for multiple employees."""
    results: Dict[str, Dict[str, Any]] = {}
    for emp in employees:
        uid = emp.get("uid")
        if not uid:
            continue
        try:
            results[str(uid)] = predict_employee(
                model, emp, competency_targets
            )
        except Exception as e:
            results[str(uid)] = {"error": str(e)}
    return results


def load_model(model_path: str = None) -> Any:
    """Load a trained model from disk."""
    if model_path is None:
        model_path = "ml/models/random_forest_model.pkl"
    path = Path(model_path)
    if not path.exists():
        raise FileNotFoundError(f"Model not found: {path}")
    with open(path, "rb") as f:
        model = pickle.load(f)
    return model
'''

with open("ml/predict.py", "a") as f:
    f.write(p4)
print("Part 4 written")

p5 = '''

def train_on_the_fly(
    training_data: List[Dict[str, Any]],
) -> Any:
    """Train a RandomForestClassifier on provided data."""
    from sklearn.ensemble import RandomForestClassifier
    X = [
        [float(r.get(f, 0.0)) for f in FEATURE_NAMES]
        for r in training_data
    ]
    y = [str(r.get("target_class", "")) for r in training_data]
    model = RandomForestClassifier(**MODEL_PARAMS)
    model.fit(X, y)
    return model


def main():
    """CLI entry point - reads JSON from stdin, outputs predictions."""
    sys.path.insert(0, str(Path(__file__).parent))
    try:
        import sklearn  # noqa: F401
    except ImportError as e:
        print(json.dumps({"error": f"scikit-learn not installed: {e}"}))
        sys.exit(1)

    try:
        payload = json.load(sys.stdin)
    except json.JSONDecodeError as e:
        print(json.dumps({"error": f"Invalid JSON: {e}"}))
        sys.exit(1)

    employees = payload.get("employees", [])
    competency_targets = payload.get("competency_targets")
    if not employees:
        print(json.dumps({"error": "No employees provided"}))
        sys.exit(1)

    training_data = payload.get("training", [])
    model_version = MODEL_VERSION
    model = None

    if training_data:
        if len(training_data) < 4:
            print(json.dumps({
                "error": f"Need at least 4 training samples, got {len(training_data)}"
            }))
            sys.exit(1)
        model = train_on_the_fly(training_data)
        model_version = "trained-now"
    else:
        try:
            model = load_model()
        except FileNotFoundError:
            model = None
            model_version = "threshold-only"
        except Exception as e:
            print(json.dumps({"error": f"Failed to load model: {e}"}))
            sys.exit(1)

    predictions = predict_all(model, employees, competency_targets)
    output = {
        "predictions": predictions,
        "model": "random_forest",
        "model_version": model_version,
        "competency_categories": list(COMPETENCY_CATEGORIES),
        "classes": list(CLASSES),
    }
    json.dump(output, sys.stdout, indent=2)


if __name__ == "__main__":
    main()
'''

with open("ml/predict.py", "a") as f:
    f.write(p5)
print("predict.py complete!")




