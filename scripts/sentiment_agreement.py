"""Compare two independent sentiment label sheets and prepare adjudication.

Reads items.csv, labels-a.csv, labels-b.csv and suggested.csv from a curated
set directory, writes agreement.md (Cohen's kappa) and adjudication.csv.
Standard library only.
"""

import argparse
import csv
import sys
from collections import Counter
from pathlib import Path

LABELS = ("positive", "neutral", "negative")


def read_rows(path: Path) -> list[dict[str, str]]:
    with path.open(encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def read_labels(path: Path, ids: list[str], errors: list[str]) -> dict[str, str]:
    labels: dict[str, str] = {}
    invalid: list[str] = []
    for row in read_rows(path):
        text_id = (row.get("text_id") or "").strip()
        label = (row.get("label") or "").strip().lower()
        if text_id in labels:
            errors.append(f"{path.name}: duplicate {text_id}")
        elif label not in LABELS:
            invalid.append(text_id or "?")
        labels[text_id] = label
    if invalid:
        errors.append(f"{path.name}: {len(invalid)} rows without positive/neutral/negative, e.g. {', '.join(invalid[:5])}")
    missing = [text_id for text_id in ids if text_id not in labels]
    unknown = [text_id for text_id in labels if text_id not in set(ids)]
    if missing:
        errors.append(f"{path.name}: missing {len(missing)} rows, e.g. {', '.join(missing[:5])}")
    if unknown:
        errors.append(f"{path.name}: unknown rows {', '.join(unknown[:5])}")
    return labels


def kappa(a: list[str], b: list[str]) -> float:
    total = len(a)
    if total == 0:
        return 0.0
    observed = sum(x == y for x, y in zip(a, b)) / total
    count_a, count_b = Counter(a), Counter(b)
    expected = sum(count_a[label] * count_b[label] for label in LABELS) / (total * total)
    return 1.0 if expected == 1 else (observed - expected) / (1 - expected)


def report(items: list[dict[str, str]], a: dict[str, str], b: dict[str, str]) -> str:
    ids = [item["text_id"] for item in items]
    pairs = [(a[i], b[i]) for i in ids]
    agreed = sum(x == y for x, y in pairs)
    lines = [
        "# Labeller Agreement",
        "",
        f"Rows: {len(ids)}. Agreed: {agreed}. To adjudicate: {len(ids) - agreed}.",
        "",
        f"Cohen's kappa overall: **{kappa([x for x, _ in pairs], [y for _, y in pairs]):.3f}**",
        "",
        "| Language | Rows | Agreed | Kappa |",
        "|---|---|---|---|",
    ]
    for language in sorted({item["language"] for item in items}):
        subset = [item["text_id"] for item in items if item["language"] == language]
        sa, sb = [a[i] for i in subset], [b[i] for i in subset]
        lines.append(f"| {language} | {len(subset)} | {sum(x == y for x, y in zip(sa, sb))} | {kappa(sa, sb):.3f} |")
    lines += ["", "Confusion (rows: labeller A, columns: labeller B):", "", "| A \\ B | " + " | ".join(LABELS) + " |", "|---" * (len(LABELS) + 1) + "|"]
    matrix = Counter(pairs)
    for label in LABELS:
        lines.append(f"| {label} | " + " | ".join(str(matrix[(label, other)]) for other in LABELS) + " |")
    lines += ["", "A kappa below 0.60 means the guide needs clarifying before the set is locked.", ""]
    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("directory", type=Path, nargs="?", default=Path("resources/sentiment/eval/curated-v1"))
    parser.add_argument("--force", action="store_true", help="overwrite an existing adjudication.csv")
    args = parser.parse_args()
    directory: Path = args.directory

    items = read_rows(directory / "items.csv")
    ids = [item["text_id"] for item in items]
    errors: list[str] = []
    a = read_labels(directory / "labels-a.csv", ids, errors)
    b = read_labels(directory / "labels-b.csv", ids, errors)
    suggested = {row["text_id"]: row["label"] for row in read_rows(directory / "suggested.csv")}
    if errors:
        print("Fix the label sheets first:", *errors, sep="\n  ", file=sys.stderr)
        return 1

    adjudication = directory / "adjudication.csv"
    if adjudication.exists() and not args.force:
        print(f"{adjudication} already exists; pass --force to rebuild it.", file=sys.stderr)
        return 1

    (directory / "agreement.md").write_text(report(items, a, b), encoding="utf-8")
    with adjudication.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.writer(handle, lineterminator="\n")
        writer.writerow(["text_id", "text", "label_a", "label_b", "suggested", "final"])
        for item in items:
            i = item["text_id"]
            writer.writerow([i, item["text"], a[i], b[i], suggested.get(i, ""), a[i] if a[i] == b[i] else ""])

    open_rows = sum(a[i] != b[i] for i in ids)
    print(f"Wrote agreement.md and adjudication.csv; {open_rows} rows need a final label.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
