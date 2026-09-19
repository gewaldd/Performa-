"""
Performa ML Engine - Model Training Pipeline
Location: ml/train.py

Manuscript Alignment:
- Trains Scikit-Learn RandomForestClassifier on synthetic 5-competency dataset.
- Evaluates Gini Impurity feature importance & Permutation Feature Importance.
- Saves serialized model bundle using joblib to ml/models/random_forest_v1.joblib.
"""

import os
import joblib
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report, accuracy_score
from sklearn.model_selection import train_test_split
from sklearn.inspection import permutation_importance

from config import COMPETENCY_CATEGORIES, MODEL_PATH, MODEL_VERSION

DATA_PATH = os.path.join(os.path.dirname(__file__), "data", "synthetic_kpi_dataset.csv")


def train_model():
    if not os.path.exists(DATA_PATH):
        raise FileNotFoundError(f"Training dataset not found at {DATA_PATH}. Run generate_synthetic_data.py first.")

    df = pd.read_csv(DATA_PATH)

    # Features: 5 competency scores
    X = df[COMPETENCY_CATEGORIES]
    # Target: overall performance classification label
    y = df["target_classification"]

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )

    clf = RandomForestClassifier(
        n_estimators=100,
        max_depth=10,
        criterion="gini",
        random_state=42
    )

    clf.fit(X_train, y_train)

    y_pred = clf.predict(X_test)
    acc = accuracy_score(y_test, y_pred)
    print(f"Model Training Complete. Test Accuracy: {acc * 100:.2f}%")
    print("\nClassification Report:\n", classification_report(y_test, y_pred))

    # Gini Impurity Feature Importance
    gini_importances = dict(zip(COMPETENCY_CATEGORIES, clf.feature_importances_))

    # Permutation Importance (as required by manuscript)
    perm_importance = permutation_importance(clf, X_test, y_test, n_repeats=10, random_state=42)
    perm_importances = dict(zip(COMPETENCY_CATEGORIES, perm_importance.importances_mean))

    # Create export bundle
    os.makedirs(os.path.dirname(MODEL_PATH), exist_ok=True)
    bundle = {
        "model": clf,
        "feature_importances": gini_importances,
        "permutation_importances": perm_importances,
        "version": MODEL_VERSION,
        "accuracy": float(acc)
    }

    joblib.dump(bundle, MODEL_PATH)
    print(f"Saved model bundle successfully to: {MODEL_PATH}")


if __name__ == "__main__":
    train_model()