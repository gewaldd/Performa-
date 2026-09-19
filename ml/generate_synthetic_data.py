"""
Performa ML Engine - Synthetic Dataset Generator
Location: ml/generate_synthetic_data.py
"""

import os
import numpy as np
import pandas as pd
from config import COMPETENCY_CATEGORIES, DATA_PATH


def generate_dataset(num_samples=1000):
    np.random.seed(42)

    data = []
    for _ in range(num_samples):
        # Generate random category scores between 1.0 and 5.0
        scores = np.random.uniform(1.0, 5.0, len(COMPETENCY_CATEGORIES))
        mean_score = float(np.mean(scores))

        if mean_score >= 3.5:
            target = "meets_expectations"
        elif mean_score >= 2.5:
            target = "needs_improvement"
        else:
            target = "critical_gap"

        row = dict(zip(COMPETENCY_CATEGORIES, np.round(scores, 2)))
        row["target_classification"] = target
        data.append(row)

    df = pd.DataFrame(data)

    # Ensure target directory exists
    os.makedirs(os.path.dirname(DATA_PATH), exist_ok=True)
    df.to_csv(DATA_PATH, index=False)
    print(f"Dataset successfully created at: {DATA_PATH}")


if __name__ == "__main__":
    generate_dataset()