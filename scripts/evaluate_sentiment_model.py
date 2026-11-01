"""Evaluate sentiment predictions against a labelled dataset and emit a reproducible report.

Predictions come from `php artisan casa:sentiment-predictions` and line up with
the dataset row by row. With --baseline (the rules predictions on the same
dataset) the report includes the promotion gate from SENTIMENT_DATA_GOVERNANCE.md.
Standard library only.
"""

import argparse
import csv
import json
import sys
from collections import Counter
from pathlib import Path

MIN_MACRO_F1 = 0.80
MAX_NEGATIVE_RECALL_DROP = 0.05


def read_rows(path: Path) -> list[dict[str, str]]:
    with path.open(encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def read_predictions(path: Path, expected: int) -> list[str]:
    labels = [row["label"].strip().lower() for row in read_rows(path)]
    if len(labels) != expected:
        raise SystemExit(f"{path} has {len(labels)} predictions for {expected} dataset rows.")
    return labels


def classification_report(truth: list[str], predicted: list[str]) -> dict:
    labels = sorted(set(truth) | set(predicted))
    report: dict = {}
    for label in labels:
        tp = sum(t == label and p == label for t, p in zip(truth, predicted))
        predicted_count = predicted.count(label)
        support = truth.count(label)
        precision = tp / predicted_count if predicted_count else 0.0
        recall = tp / support if support else 0.0
        f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
        report[label] = {"precision": precision, "recall": recall, "f1-score": f1, "support": support}
    total = len(truth)
    report["accuracy"] = sum(t == p for t, p in zip(truth, predicted)) / total if total else 0.0
    for name, weight in (("macro avg", lambda s: 1 / len(labels)), ("weighted avg", lambda s: s / total)):
        report[name] = {
            metric: sum(report[label][metric] * weight(report[label]["support"]) for label in labels) if total else 0.0
            for metric in ("precision", "recall", "f1-score")
        }
        report[name]["support"] = total
    return report


def summary(truth: list[str], predicted: list[str]) -> dict:
    report = classification_report(truth, predicted)
    return {
        "rows": len(truth),
        "errors": sum(t != p for t, p in zip(truth, predicted)),
        "accuracy": round(report["accuracy"], 4),
        "macro_f1": round(report["macro avg"]["f1-score"], 4),
    }


def breakdowns(rows: list[dict[str, str]], truth: list[str], predicted: list[str]) -> dict:
    groups: dict[str, dict[str, list[int]]] = {"language": {}, "tag": {}}
    for index, row in enumerate(rows):
        groups["language"].setdefault(row.get("language") or "unknown", []).append(index)
        for tag in filter(None, (row.get("tags") or "").split("|")):
            groups["tag"].setdefault(tag, []).append(index)
    return {
        kind: {name: summary([truth[i] for i in indexes], [predicted[i] for i in indexes]) for name, indexes in sorted(members.items())}
        for kind, members in groups.items()
    }


def gate(result: dict, baseline: dict) -> dict:
    def negative_recall(evaluation: dict) -> float:
        return evaluation["classification_report"].get("negative", {}).get("recall", 0.0)

    def errors(evaluation: dict, kind: str, name: str) -> int | None:
        group = evaluation["breakdowns"][kind].get(name)
        return group["errors"] if group else None

    macro_f1 = result["classification_report"]["macro avg"]["f1-score"]
    checks = [
        {"check": f"macro-F1 >= {MIN_MACRO_F1:.2f}", "value": round(macro_f1, 4), "passed": macro_f1 >= MIN_MACRO_F1},
        {
            "check": "negative recall within 5 points of rules",
            "value": round(negative_recall(result), 4),
            "baseline": round(negative_recall(baseline), 4),
            "passed": negative_recall(result) >= negative_recall(baseline) - MAX_NEGATIVE_RECALL_DROP,
        },
    ]
    for kind, name in (("language", "taglish"), ("tag", "mixed")):
        value, base = errors(result, kind, name), errors(baseline, kind, name)
        checks.append({
            "check": f"fewer {name} errors than rules",
            "value": value,
            "baseline": base,
            "passed": value is not None and base is not None and (value < base or value == base == 0),
        })
    return {"passed": all(check["passed"] for check in checks), "checks": checks}


def evaluate(rows: list[dict[str, str]], truth: list[str], predicted: list[str]) -> dict:
    report = classification_report(truth, predicted)
    labels = sorted(set(truth) | set(predicted))
    matrix = Counter(zip(truth, predicted))
    return {
        "classification_report": report,
        "confusion_matrix": {"labels": labels, "rows": [[matrix[(t, p)] for p in labels] for t in labels]},
        "breakdowns": breakdowns(rows, truth, predicted),
        "rows": len(rows),
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("dataset", type=Path)
    parser.add_argument("predictions", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--baseline", type=Path, help="rules predictions on the same dataset; adds the promotion gate")
    args = parser.parse_args()

    rows = read_rows(args.dataset)
    truth = [row["label"].strip().lower() for row in rows]
    result = evaluate(rows, truth, read_predictions(args.predictions, len(rows)))
    if args.baseline:
        baseline = evaluate(rows, truth, read_predictions(args.baseline, len(rows)))
        result["baseline"] = {"macro_f1": round(baseline["classification_report"]["macro avg"]["f1-score"], 4), "breakdowns": baseline["breakdowns"]}
        result["gate"] = gate(result, baseline)
    args.output.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")

    report = result["classification_report"]
    print(f"rows {len(rows)}  accuracy {report['accuracy']:.3f}  macro-F1 {report['macro avg']['f1-score']:.3f}")
    if "gate" in result:
        for check in result["gate"]["checks"]:
            mark = "PASS" if check["passed"] else "FAIL"
            base = f" (rules {check['baseline']})" if "baseline" in check else ""
            print(f"  {mark}  {check['check']}: {check['value']}{base}")
        print("Gate: " + ("PASSED" if result["gate"]["passed"] else "FAILED"))
        sys.exit(0 if result["gate"]["passed"] else 2)


if __name__ == "__main__":
    main()
