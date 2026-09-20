"""
Performa ML Engine - Synthetic Dataset Generator
Location: ml/generate_synthetic_data.py

Manuscript alignment:
- 5 competencies, per-competency labels via classify_score(score, target=4.0)
  with target-0.8 buffer (matches kpi_templates.php).
- Overall month label via labels.overall_label_from_per_category() so the RF
  must learn feature interactions, NOT a trivial mean() threshold.
- Correlated skill-factor + noise produces realistic SME rating patterns
  instead of independent uniform noise.
"""

import os
import sys

import numpy as np
import pandas as pd

try:
    from config import COMPETENCY_CATEGORIES, DATA_PATH, DEFAULT_COMPETENCY_TARGET
    from labels import overall_label_from_per_category, per_category_labels
except ImportError:
    from ml.config import COMPETENCY_CATEGORIES, DATA_PATH, DEFAULT_COMPETENCY_TARGET
    from ml.labels import overall_label_from_per_category, per_category_labels


def generate_dataset(num_samples: int = 1000, seed: int = 42) -> str:
    rng = np.random.default_rng(seed)

    # Mix of performer archetypes so all 3 classes appear naturally
    archetypes = ["strong", "average", "struggling", "uneven", "new_hire"]
    archetype_probs = [0.30, 0.30, 0.15, 0.15, 0.10]
    skill_means = {"strong": 4.3, "average": 3.5, "struggling": 2.2, "uneven": 3.2, "new_hire": 2.8}
    skill_stds = {"strong": 0.45, "average": 0.55, "struggling": 0.60, "uneven": 1.05, "new_hire": 0.80}

    targets = {c: DEFAULT_COMPETENCY_TARGET for c in COMPETENCY_CATEGORIES}
    rows = []
    for _ in range(num_samples):
        arch = str(rng.choice(archetypes, p=archetype_probs))
        # Latent skill + per-competency noise + occasional rater noise outlier
        skill = float(rng.normal(skill_means[arch], 0.35))
        scores: dict = {}
        for comp in COMPETENCY_CATEGORIES:
            val = float(rng.normal(skill, skill_stds[arch]))
            if rng.random() < 0.04:  # supervisor entry noise / outlier
                val += float(rng.normal(0.0, 1.1))
            scores[comp] = float(np.clip(round(val, 2), 1.0, 5.0))

        per_cat = per_category_labels(scores, targets)
        overall = overall_label_from_per_category(per_cat)
        row = {c: scores[c] for c in COMPETENCY_CATEGORIES}
        row["target_classification"] = overall
        # Keep per-competency audit columns for transparency (not model features)
        for c in COMPETENCY_CATEGORIES:
            row[f"label_{c}"] = per_cat[c]
        rows.append(row)

    df = pd.DataFrame(rows)
    os.makedirs(os.path.dirname(DATA_PATH), exist_ok=True)
    df.to_csv(DATA_PATH, index=False)
    dist = df["target_classification"].value_counts().to_dict()
    print(f"Dataset successfully created at: {DATA_PATH}")
    print(f"Samples: {len(df)} | Distribution: {dist}")
    return DATA_PATH


if __name__ == "__main__":
    n = int(sys.argv[1]) if len(sys.argv) > 1 else 1000
    generate_dataset(num_samples=n)
