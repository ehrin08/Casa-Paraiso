"""Lock an adjudicated sentiment test set and print its SHA-256.

Reads items.csv and adjudication.csv from a curated set directory and writes
locked-test.csv once every row has a final label. It never overwrites an
existing locked file. Standard library only.
"""

import argparse
import csv
import hashlib
import sys
from collections import Counter
from pathlib import Path

LABELS = ("positive", "neutral", "negative")
COLUMNS = ["text_id", "text", "rating", "label", "language", "topics", "tags", "split"]


def read_rows(path: Path) -> list[dict[str, str]]:
    with path.open(encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("directory", type=Path, nargs="?", default=Path("resources/sentiment/eval/curated-v1"))
    args = parser.parse_args()
    directory: Path = args.directory
    locked = directory / "locked-test.csv"
    if locked.exists():
        print(f"{locked} is already locked. A corrected set needs a new version directory.", file=sys.stderr)
        return 1

    items = read_rows(directory / "items.csv")
    finals = {row["text_id"]: (row.get("final") or "").strip().lower() for row in read_rows(directory / "adjudication.csv")}
    problems = [item["text_id"] for item in items if finals.get(item["text_id"]) not in LABELS]
    if problems:
        print(f"{len(problems)} rows have no valid final label, e.g. {', '.join(problems[:5])}", file=sys.stderr)
        return 1

    with locked.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle, lineterminator="\n")
        writer.writerow(COLUMNS)
        for item in items:
            writer.writerow([item["text_id"], item["text"], item["rating"], finals[item["text_id"]], item["language"], item["topics"], item["tags"], "test"])

    digest = hashlib.sha256(locked.read_bytes()).hexdigest()
    counts = Counter(finals[item["text_id"]] for item in items)
    print(f"Locked {len(items)} rows to {locked}")
    print("Labels: " + ", ".join(f"{label} {counts[label]}" for label in LABELS))
    print(f"sha256: {digest}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
