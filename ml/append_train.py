#!/usr/bin/env python3
"""Helper script to create/update ML module files."""
import os
from pathlib import Path

ML_DIR = Path(r"D:\Downloads\odysseus-dev\Performa-\ml")

def append_to_file(filename: str, content: str):
    """Append content to a file."""
    filepath = ML_DIR / filename
    with open(filepath, "a", encoding="utf-8") as f:
        f.write(content)
    print(f"Appended to {filename}")

# Content to append to train.py
train_append = """

def save_model(
    model,
    metrics,
    model_dir="ml/models",
    version="1.0.0",
) -> str:
    \"\"\"Save the trained model and metadata to disk.\"\"\"
    model_path = Path(model_dir)
    model_path.mkdir(parents=True, exist_ok=True)
    
    model_file = model_path / "random_forest_model.pkl"
    with open(model_file, "wb") as f:
        pickle.dump(model, f)
    
    metadata_file = model_path / "model_metadata.json"
    with open(metadata_file, "w") as f:
        json.dump({
            "model_type": "RandomForestClassifier",
            "version": version,
            "trained_at": datetime.now(timezone.utc).isoformat(),
            "model_params": MODEL_PARAMS,
            "feature_names": list(FEATURE_NAMES),
            "classes": list(CLASSES),
            "competency_categories": list(COMPETENCY_CATEGORIES),
        }, f, indent=2)
    
    print(f"Model saved to {model_path}")
    return str(model_path)


def load_model(model_dir="ml/models"):
    \"\"\"Load a trained model and its metadata from disk.\"\"\"
    model_path = Path(model_dir)
    model_file = model_path / "random_forest_model.pkl"
    metadata_file = model_path / "model_metadata.json"
    
    if not model_file.exists():
        raise FileNotFoundError(f"Model not found: {model_file}")
    
    with open(model_file, "rb") as f:
        model = pickle.load(f)
    with open(metadata_file, "r") as f:
        metadata = json.load(f)
    
    return model, metadata


def generate_feature_importance_report(model, X, y):
    \"\"\"Generate feature importance analysis.\"\"\"
    gini = dict(zip(FEATURE_NAMES, model.feature_importances_.tolist()))
    perm = permutation_importance(model, X, y, n_repeats=10, random_state=42)
    perm_mean = dict(zip(FEATURE_NAMES, perm.importances_mean.tolist()))
    perm_std = dict(zip(FEATURE_NAMES, perm.importances_std.tolist()))
    total = sum(gini.values())
    normalized = {k: v/total if total > 0 else 0 for k, v in gini.items()}
    sorted_gini = sorted(gini.items(), key=lambda x: x[1], reverse=True)
    
    return {
        "gini_importance": gini,
        "normalized_gini_importance": normalized,
        "permutation_importance_mean": perm_mean,
        "permutation_importance_std": perm_std,
        "ranked_features": [{"feature": f, "gini_importance": i} for f, i in sorted_gini],
    }


def main():
    \"\"\"Main entry point for training.\"\"\"
    import argparse
    
    parser = argparse.ArgumentParser(description="Train Performa RF model")
    parser.add_argument("--data", default="ml/training_data.json")
    parser.add_argument("--output", default="ml/models")
    parser.add_argument("--version", default="1.0.0")
    args = parser.parse_args()
    
    data_file = Path(args.data)
    if not data_file.exists():
        print(f"Error: {args.data} not found", file=sys.stderr)
        sys.exit(1)
    
    with open(data_file) as f:
        training_data = json.load(f)
    print(f"Loaded {len(training_data)} training records")
    
    model, metrics = train_model(training_data)
    
    X = np.array(extract_features(training_data))
    y = np.array(extract_targets(training_data))
    fi = generate_feature_importance_report(model, X, y)
    
    print("\\nFeature Importance (Gini):")
    for feat, imp in sorted(fi["gini_importance"].items(), key=lambda x: x[1], reverse=True):
        print(f"  {feat}: {imp:.4f} ({fi['normalized_gini_importance'][feat]:.1%})")
    
    save_model(model, metrics, args.output, args.version)
    print("\\nTraining complete!")
    return 0


if __name__ == "__main__":
    sys.exit(main())
"""

append_to_file("train.py", train_append)
print("\\nDone!")
