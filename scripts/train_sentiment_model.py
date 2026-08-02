"""Train an offline local sentiment artifact; never run this in production."""

import argparse
import csv
import json
from datetime import date
from pathlib import Path

import numpy as np
from scipy.sparse import hstack
from sklearn.feature_extraction.text import CountVectorizer
from sklearn.linear_model import LogisticRegression


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("dataset", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()

    rows = list(csv.DictReader(args.dataset.open(encoding="utf-8", newline="")))
    texts = [row["text"].lower() for row in rows]
    labels = [row["label"] for row in rows]
    word = CountVectorizer(binary=True, ngram_range=(1, 2), token_pattern=r"(?u)\b\w+\b")
    char = CountVectorizer(binary=True, analyzer="char", ngram_range=(3, 5))
    matrix = hstack([word.fit_transform(texts), char.fit_transform(texts)])
    ratings = np.asarray([[int(row["rating"]) == value for value in range(1, 6)] for row in rows], dtype=float)
    matrix = hstack([matrix, ratings]).tocsr()
    model = LogisticRegression(max_iter=1000, multi_class="multinomial").fit(matrix, labels)
    names = [f"word::{name}" for name in word.get_feature_names_out()]
    names += [f"char::{name}" for name in char.get_feature_names_out()]
    names += [f"rating_{value}" for value in range(1, 6)]
    weights = {name: [float(value) for value in row] for name, row in zip(names, model.coef_.T)}
    artifact = {
        "version": "model-v1.0.0",
        "labels": list(model.classes_),
        "feature_contract": "binary_word_1_2_char_3_5_plus_rating",
        "trained_at": date.today().isoformat(),
        "dataset_manifest": "docs/sentiment-dataset-manifest.json",
        "bias": [float(value) for value in model.intercept_],
        "weights": weights,
        "rows": len(rows),
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(artifact, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
