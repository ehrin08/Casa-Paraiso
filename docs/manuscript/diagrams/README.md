# Manuscript diagrams

These are the sources for Figure 1 (Input-Process-Output) and the Chapter III figures listed in the [Chapter III diagram guide](../2026-11-02-chapter3-diagrams.md).

- `svgkit.py` is a small fixed-layout SVG toolkit with boxes, decisions, data stores, actors, ERD entities, crow's-foot relationships and arrows. It renders each SVG to PNG with local headless Chrome.
- `build.py` defines one function per figure, each with explicit coordinates on a canvas 720 units wide. That width matches the 6-inch text width, so a 12-unit font prints at about 7 pt.

Regenerate every figure, or only the ones you name by number:

```bash
cd docs/manuscript/diagrams
python build.py ../../../output/questionnaire/figures/diagrams
python build.py ../../../output/questionnaire/figures/diagrams 06 12b
```

The script expects Chrome at `C:/Program Files/Google/Chrome/Application/chrome.exe`; change `CHROME` in `svgkit.py` on another machine. The output folder is ignored by git.

Keep the diagrams faithful to the code. When a service, table or rule changes, update the matching function and rerun the script.
