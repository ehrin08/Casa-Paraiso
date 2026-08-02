"""Evaluate a local sentiment model dataset and emit a reproducible report."""

import argparse
import csv
import json
import time
from pathlib import Path

from sklearn.metrics import classification_report, confusion_matrix


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("dataset", type=Path)
    parser.add_argument("predictions", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    rows = list(csv.DictReader(args.dataset.open(encoding="utf-8", newline="")))
    predictions = list(csv.DictReader(args.predictions.open(encoding="utf-8", newline="")))
    truth = [row["label"] for row in rows]
    predicted = [row["label"] for row in predictions]
    started = time.perf_counter()
    report = classification_report(truth, predicted, output_dict=True, zero_division=0)
    elapsed_ms = (time.perf_counter() - started) * 1000
    args.output.write_text(json.dumps({"classification_report": report, "confusion_matrix": confusion_matrix(truth, predicted).tolist(), "evaluation_ms": round(elapsed_ms, 3), "rows": len(rows)}, indent=2) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
