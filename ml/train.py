"""
Performa ML Engine - Model Training Pipeline
Location: ml/train.py

Manuscript alignment (Final Manuscript Sept 2026):
- RandomForestClassifier with Table 4 Optimized params: 100 / max_depth 8 / min_samples_split 2
- 70/15/15 train/test/val split (P.290), stratified
- Metrics: accuracy, precision, recall, F1 (macro) on test + val
- Gini + Permutation importance (P.502-504) saved for Article 296 audit trail
- Bundle: ml/models/random_forest_v1.joblib
"""

import os
import sys

import joblib
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.inspection import permutation_importance
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    f1_score,
    precision_score,
    recall_score,
)
from sklearn.model_selection import train_test_split

try:
    from config import (
        COMPETENCY_CATEGORIES,
        DATA_PATH,
        MODEL_PARAMS,
        MODEL_PATH,
        MODEL_VERSION,
        NUM_PERMUTATION_ITERATIONS,
        RANDOM_SEED,
    )
except ImportError:
    from ml.config import (
        COMPETENCY_CATEGORIES,
        DATA_PATH,
        MODEL_PARAMS,
        MODEL_PATH,
        MODEL_VERSION,
        NUM_PERMUTATION_ITERATIONS,
        RANDOM_SEED,
    )


def train_model(data_path: str | None = None, model_path: str | None = None) -> dict:
    data_path = data_path or DATA_PATH
    model_path = model_path or MODEL_PATH

    if not os.path.exists(data_path):
        raise FileNotFoundError(
            f"Training dataset not found at {data_path}. Run generate_synthetic_data.py first."
        )

    df = pd.read_csv(data_path)
    feature_cols = [c for c in COMPETENCY_CATEGORIES if c in df.columns]
    if len(feature_cols) != len(COMPETENCY_CATEGORIES):
        raise ValueError(f"Dataset missing competency columns. Found: {list(df.columns)}")
    label_col = "target_classification" if "target_classification" in df.columns else "target_class"
    X = df[feature_cols]
    y = df[label_col]

    # 70/15/15: first split off 30% (test+val), then halve it stratified
    X_train, X_temp, y_train, y_temp = train_test_split(
        X, y, test_size=0.30, random_state=RANDOM_SEED, stratify=y
    )
    X_test, X_val, y_test, y_val = train_test_split(
        X_temp, y_temp, test_size=0.50, random_state=RANDOM_SEED, stratify=y_temp
    )
    print(f"Split: train={len(X_train)} test={len(X_test)} val={len(X_val)}")

    clf = RandomForestClassifier(**MODEL_PARAMS)
    clf.fit(X_train, y_train)

    def _metrics(Xe, ye, name: str) -> dict:
        pred = clf.predict(Xe)
        m = {
            "accuracy": float(accuracy_score(ye, pred)),
            "precision_macro": float(precision_score(ye, pred, average="macro", zero_division=0)),
            "recall_macro": float(recall_score(ye, pred, average="macro", zero_division=0)),
            "f1_macro": float(f1_score(ye, pred, average="macro", zero_division=0)),
        }
        print(f"\n[{name}] acc={m['accuracy']:.4f} prec={m['precision_macro']:.4f} "
              f"rec={m['recall_macro']:.4f} f1={m['f1_macro']:.4f}")
        print(classification_report(ye, pred, zero_division=0))
        return m

    test_m = _metrics(X_test, y_test, "TEST")
    val_m = _metrics(X_val, y_val, "VAL")

    gini_importances = dict(zip(feature_cols, [float(v) for v in clf.feature_importances_]))
    perm = permutation_importance(
        clf, X_test, y_test, n_repeats=NUM_PERMUTATION_ITERATIONS, random_state=RANDOM_SEED
    )
    perm_importances = dict(zip(feature_cols, [float(v) for v in perm.importances_mean]))
    perm_std = dict(zip(feature_cols, [float(v) for v in perm.importances_std]))

    print("\nFeature Importance (Gini):")
    for k, v in sorted(gini_importances.items(), key=lambda x: x[1], reverse=True):
        print(f"  {k}: {v:.4f}")
    print("\nPermutation Importance (mean):")
    for k, v in sorted(perm_importances.items(), key=lambda x: x[1], reverse=True):
        print(f"  {k}: {v:.4f} (+/- {perm_std[k]:.4f})")

    os.makedirs(os.path.dirname(os.path.abspath(model_path)), exist_ok=True)
    bundle = {
        "model": clf,
        "feature_names": feature_cols,
        "feature_importances": gini_importances,
        "permutation_importances": perm_importances,
        "permutation_std": perm_std,
        "version": MODEL_VERSION,
        "params": dict(MODEL_PARAMS),
        "metrics": {"test": test_m, "val": val_m},
        "accuracy": float(test_m["accuracy"]),
        "label_column": label_col,
    }
    joblib.dump(bundle, model_path)
    print(f"\nSaved model bundle to: {model_path} (v{MODEL_VERSION})")
    return bundle


if __name__ == "__main__":
    dp = sys.argv[1] if len(sys.argv) > 1 else None
    mp = sys.argv[2] if len(sys.argv) > 2 else None
    train_model(dp, mp)
