"""Train a Random Forest on labeled Performa employees and predict current cases.

The source of this file had been deleted; its interface was reconstructed from
the surviving bytecode artifact ``ml/__pycache__/predict.cpython-314.pyc``
(Python 3.14). Recovered as ground truth from that artifact: the FEATURE_NAMES
tuple and its order, the stdin/stdout JSON shape, the non-zero exit code on
failure, the exact RuntimeError strings, the four RandomForestClassifier keyword
names, the three canonical label strings, and the
``json.dump({"predictions": ...}, sys.stdout)`` tail. Keep this contract stable.

Protocol
--------
stdin   : {"training":  [ {<feature>: <num>, ..., "label": <str>}, ... ],
           "employees": [ {"uid": <str>, <feature>: <num>, ...}, ... ]}
stdout  : {"predictions": {<uid>: {"label": <str>,
                                   "confidence": <float>,
                                   "training_examples": <int>,
                                   "model": "random_forest"}}}
failure : {"error": <str>} on stdout, exit code 1.

Every name in FEATURE_NAMES must be present and numeric on both the training
rows and the employee rows.

No caller exists in the PHP application yet -- nothing under *.php shells out to
this script today. It is invoked manually / by whatever wires it up next.
"""

import json
import sys

FEATURE_NAMES = (
    "average_score",
    "minimum_score",
    "maximum_score",
    "score_spread",
    "rating_count",
    "days_since_start",
    "days_left",
    "target_average",
)

# Hyper-parameters.
#
# The bytecode proves the KEYWORD NAMES below but not their values -- those were
# not recoverable. The choices are deliberate and documented so they can be
# argued with rather than guessed at:
#   n_estimators=300        enough trees that a handful of labeled rows still
#                           yields a stable vote; trivial cost at this size.
#   random_state=42         reproducibility. The same input must produce the
#                           same recommendation twice (demo/defense), and a
#                           non-deterministic model cannot be tested.
#   class_weight="balanced" regularization decisions are lopsided in practice
#                           (most probationary staff are retained), so without
#                           this the majority class swamps 'needs_training'.
#   min_samples_leaf=2      one outlier rating must not own a leaf; single-row
#                           leaves overfit hard on 4-20 training rows.
MODEL_PARAMS = {
    "n_estimators": 300,
    "random_state": 42,
    "class_weight": "balanced",
    "min_samples_leaf": 2,
}

# Canonical decision vocabulary. All three strings appear in the original
# bytecode's constants. Raw model labels are folded onto these so callers never
# have to know how the training set spelled things.
LABEL_RECOMMENDED = "recommended"
LABEL_READY = "ready_for_regularization"
LABEL_NEEDS_TRAINING = "needs_training"

# Raw class label -> canonical label.
#
# DOCUMENTED GAP: the bytecode proves these three canonical strings exist and
# that every prediction is stamped with one of them, but it does not encode
# WHICH raw label maps to WHICH target. The mapping below is the
# human-readable-intent reading of the names. This is the only place to change
# if the training set uses different spellings.
_LABEL_ALIASES = {
    "recommended": LABEL_RECOMMENDED,
    "recommend_for_regularization": LABEL_RECOMMENDED,
    "regularize": LABEL_RECOMMENDED,
    "regularization": LABEL_RECOMMENDED,
    "pass": LABEL_RECOMMENDED,
    "ready": LABEL_READY,
    "ready_for_regularization": LABEL_READY,
    "ready_for_reg": LABEL_READY,
    "extend": LABEL_NEEDS_TRAINING,
    "not_recommended": LABEL_NEEDS_TRAINING,
    "needs_training": LABEL_NEEDS_TRAINING,
    "training": LABEL_NEEDS_TRAINING,
    "fail": LABEL_NEEDS_TRAINING,
}


def normalize_label(raw_label):
    """Fold a raw class label onto the canonical decision vocabulary.

    Unknown labels are passed through lowercased with spaces/dashes turned into
    underscores instead of raising: a new label appearing in the training set
    should degrade to "shows something readable", not fail the whole request.
    """
    key = str(raw_label).strip().lower().replace(" ", "_").replace("-", "_")
    return _LABEL_ALIASES.get(key, key)


def main():
    # Imported lazily so a missing scikit-learn surfaces as a JSON error with a
    # usable install command, instead of a bare ModuleNotFoundError traceback.
    try:
        from sklearn.ensemble import RandomForestClassifier
    except ImportError as exc:
        raise RuntimeError(
            "scikit-learn is not installed; run: py -m pip install -r ml/requirements.txt"
        ) from exc

    payload = json.load(sys.stdin)
    training = payload.get("training")
    employees = payload.get("employees")

    labels = {str(item.get("label")) for item in training}
    if len(training) < 4 or len(labels) < 2:
        raise RuntimeError(
            "at least four labeled employees and two decision classes are required"
        )

    features = [[float(item[name]) for name in FEATURE_NAMES] for item in training]
    targets = [str(item["label"]) for item in training]

    model = RandomForestClassifier(**MODEL_PARAMS)
    model.fit(features, targets)

    predictions = {}
    for employee in employees:
        vector = [float(employee[name]) for name in FEATURE_NAMES]
        probabilities = model.predict_proba([vector])[0]
        class_index = int(probabilities.argmax())
        raw_label = str(model.classes_[class_index])
        predictions[str(employee["uid"])] = {
            "label": normalize_label(raw_label),
            "confidence": round(float(probabilities[class_index]), 4),
            "training_examples": len(training),
            "model": "random_forest",
        }

    json.dump({"predictions": predictions}, sys.stdout)


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"error": str(exc)}))
        sys.exit(1)