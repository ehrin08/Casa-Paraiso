# Sentiment Labelling Guide

How to label feedback comments for the Casa Paraiso sentiment test sets. It applies to the curated set in `resources/sentiment/eval/curated-v1/` and to any later set built from real, redacted feedback. See [Sentiment Data Governance](../SENTIMENT_DATA_GOVERNANCE.md) for the gate and the redaction rules.

The curated set (`curated-v1`) is fictional. Its comments were written for evaluation and are not customer data.

## How to label

- Fill in only the `label` column of your own sheet (`labels-a.csv` or `labels-b.csv`). Use exactly `positive`, `neutral` or `negative`.
- Label on your own. Don't look at the other labeller's sheet or at `suggested.csv`.
- Label the text only. Ignore the `rating` column. Some comments disagree with their star rating on purpose, and the test measures how each classifier handles them.
- Label how the writer feels about the visit overall, not single words.
- The sheets open in Excel or Google Sheets. Save them as "CSV UTF-8" so Tagalog text and emoji survive, and don't reorder or delete rows.
- If you really can't decide, choose `neutral` and keep going. The disagreement report will bring the row back for adjudication.

## Labels

| Label | Use it when | Examples |
|---|---|---|
| `positive` | The writer is satisfied overall: praise, thanks, a wish to return or a recommendation. | "Ang galing ng therapist, babalik ako." / "Super relaxing, sulit." |
| `neutral` | The comment is factual, only a question or suggestion, or the praise and complaints balance out. | "Booked the 90-minute package." / "Okay lang, walang special." |
| `negative` | The writer is dissatisfied overall: a complaint, regret, a warning to others, or saying they won't return. | "Ang tagal ng hintay, hindi na ako babalik." / "Room was dirty." |

## Mixed comments

Label the overall verdict.

- Praise that outweighs a small complaint is `positive`: "Medyo late nagsimula pero ang galing ng masahe, babalik ako."
- A complaint that outweighs the praise is `negative`: "Mabait si [name] pero 40 minutes kaming naghintay at hindi na kami babalik."
- Balanced or hesitant comments are `neutral`: "Maganda ang lugar, pero mahal para sa amin."
- A closing judgement ("babalik ako", "never again", "sulit", "hindi sulit") usually decides it.

## Negation, sarcasm and spelling

- Read negation as meaning. "Hindi masama" and "not bad" are mild praise, so they're usually `positive` or `neutral`. "Hindi relaxing" is `negative`.
- Sarcasm is labelled by its intent: "Wow, isang oras lang naman kaming naghintay" is `negative`.
- Read misspellings and text-speak as intended: "sulit n sulit", "gnda", "d na babalik".
- Emoji count as words: 😡 leans negative and ❤️ leans positive, but the text decides.

## Language tags

These are set in `items.csv` and help break the results down. Labellers don't change them.

| Tag | Meaning | Examples |
|---|---|---|
| `en` | English only | "The therapist was gentle and on time." / "Waited too long at reception." / "Clean rooms, fair price." |
| `tl` | Tagalog or Filipino only | "Napakasarap ng masahe." / "Ang dumi ng banyo." / "Babalik kami ulit." |
| `taglish` | English and Tagalog mixed in one comment | "Super relaxing, sulit talaga." / "Ang tagal ng waiting time, sobrang hassle." / "Okay lang yung room pero medyo maingay." |

## Item tags

`tags` lists the hard cases a row tests, separated by `|`. The tags are `mixed`, `negation`, `sarcasm`, `misspelling` and `rating_conflict` (the text disagrees with the stars). Rows with no tags are ordinary cases.

## After labelling

1. Run `scripts/sentiment_agreement.py`. It reports Cohen's kappa and writes `adjudication.csv`. Rows where the labellers agree already have a `final` label.
2. The adjudicator fills in `final` on each row the labellers disagreed on. They may look at `suggested.csv` and this guide.
3. Run `scripts/lock_sentiment_testset.py`. It writes `locked-test.csv` and prints its SHA-256, which goes into the [dataset manifest](../sentiment-dataset-manifest.json).
4. Once the set is locked, nobody edits it or uses it to tune rules or models. A correction needs a new version, `curated-v2`.
