# Sentiment Analysis Upgrade Implementation Plan

## Purpose

Upgrade Casa Paraiso from its current deterministic sentiment rules to a measured, domain-aware sentiment system without breaking the MVP behavior or introducing a required external AI service.

The current classifier remains the production fallback throughout the upgrade. The target is a hybrid system that can learn from verified feedback while remaining explainable, affordable, and maintainable by a small business.

## Constraints and decisions

- Preserve the existing labels: `positive`, `neutral`, and `negative`.
- Preserve the current rating fallback: 4–5 positive, 3 neutral, and 1–2 negative.
- Continue supporting English, Tagalog, and mixed Taglish.
- Do not scrape arbitrary online reviews into production data.
- Use only public datasets whose licenses permit the intended research or training use.
- Anonymize Casa Paraiso comments before exporting them for annotation or training.
- Do not make an external AI service a production dependency for the MVP.
- Keep the current classifier available whenever a model is unavailable, uncertain, or below the acceptance threshold.
- Treat model output as decision support; admins can review examples and sentiment summaries.

## Target architecture

```text
Feedback submission
        |
        v
Current rules --------------------+
        |                         |
        +--> optional model ------+--> final label, score, confidence, source
                                      |
                                      v
                              feedback record + admin insights
```

The first release of this plan may use rules only while the dataset is being built. A trained model is enabled only after it passes the evaluation gate below.

## Phase 1: Data and governance foundation

1. Create a dataset manifest recording source, license, language, labels, collection date, and permitted use.
2. Identify licensed English, Filipino, and Taglish sentiment datasets for bootstrap experiments.
3. Add an internal annotation format with:
   - comment text or anonymized text ID;
   - rating;
   - human sentiment label;
   - optional aspects such as therapist, cleanliness, waiting time, price, and treatment;
   - annotator ID, timestamp, and adjudication status.
4. Define redaction for names, phone numbers, email addresses, booking numbers, and other personal information.
5. Keep external datasets separate from customer records and never import them into the production `feedback` table.

Deliverables:

- Dataset manifest and license checklist.
- Versioned annotation schema.
- Redaction and retention procedure.
- Decision log for every external dataset accepted or rejected.

## Phase 2: Build the Casa Paraiso labeling set

1. Export only anonymized historical comments, if available, or begin with new feedback as it arrives.
2. Use the current classifier and rating to pre-label comments; mark these as suggestions, not truth.
3. Prioritize human review for:
   - rule/rating conflicts;
   - mixed positive and negative comments;
   - Taglish and misspellings;
   - comments with no recognized sentiment terms;
   - low-confidence model results once a model exists.
4. Have two people label at least 10–20% of the sample independently.
5. Adjudicate disagreements and maintain a short labeling guide with examples.
6. Start with 300–500 verified comments, then expand toward 1,000 or more as real feedback accumulates.

Deliverables:

- Training set.
- Validation set.
- Locked test set that is never used for training.
- Labeling guide and agreement report.

## Phase 3: Establish a measurable baseline

1. Run the current `SentimentClassifier` against the locked test set.
2. Report accuracy, macro-F1, and per-class precision/recall.
3. Report results separately for English, Tagalog, Taglish, ratings, and mixed-polarity comments.
4. Record common errors and update rules only when the correction is explainable and covered by a regression test.
5. Do not call the model upgrade successful unless it improves macro-F1 without materially harming the neutral or negative classes.

Deliverables:

- Baseline evaluation report.
- Error analysis.
- Regression test cases for every accepted rule change.

## Phase 4: Train and compare candidate models

Evaluate candidates in this order:

1. Improved deterministic rules as the lowest-maintenance option.
2. A small supervised classifier using text features and rating.
3. A multilingual transformer or hosted model only as an optional research path, subject to privacy, cost, latency, and deployment review.

For every candidate, measure:

- macro-F1 and per-class F1;
- Tagalog and Taglish performance;
- mixed sentiment and negation performance;
- inference time and memory;
- reproducibility;
- fallback behavior;
- licensing and operational cost.

The production candidate must be deployable with the approved Laravel/MySQL web architecture without a continuously running worker or mandatory third-party AI endpoint.

## Phase 5: Application integration

Only after the evaluation gate passes:

1. Add a classifier adapter so rules and a trained model share one interface.
2. Extend feedback storage with model metadata only if needed, such as:
   - classifier version;
   - confidence;
   - classification source (`rules`, `model`, or `reviewed`);
   - reviewed label and reviewer timestamp.
3. Keep the existing `sentiment_label` and `sentiment_score` fields compatible.
4. Use a confidence threshold:
   - high confidence: save the model result;
   - low confidence or disagreement: use the rules fallback and flag for review.
5. Preserve the existing one-feedback-per-completed-appointment rule.
6. Update the historical reclassification command to support dry-run, version reporting, transition counts, and explicit apply mode.
7. Add admin views for low-confidence items, reviewed corrections, and model-version summaries.

## Phase 6: Pilot and monitoring

1. Run the new classifier in shadow mode while the current rules remain authoritative.
2. Compare model and rule outputs without changing customer-visible behavior.
3. Have admins review disagreement samples weekly during the pilot.
4. Enable the model only for a controlled percentage or selected environment after acceptance.
5. Track class distribution, confidence distribution, disagreement rate, and correction rate.
6. Roll back to the rules classifier if quality, privacy, cost, or performance regresses.

## Acceptance criteria

- Existing sentiment tests continue to pass.
- The rules fallback remains available and deterministic.
- The final classifier supports English, Tagalog, and Taglish test cases.
- The locked test set is never used for training or tuning.
- The selected approach meets the agreed macro-F1 and per-class minimums, with no unacceptable negative-class regression.
- Low-confidence and conflicting classifications are identifiable for admin review.
- No external customer comments are stored in a public dataset or sent to an unapproved service.
- Historical reclassification is dry-run by default and account/data preserving.
- Mobile and Blade feedback submission continue to store compatible labels and scores.

## Verification commands

```text
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan test
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:reclassify-sentiment
.\scripts\casa-docker.ps1 compose exec -T --user sail laravel.test php artisan casa:reclassify-sentiment --apply
```

The apply command must only be run after reviewing the dry-run transitions and creating the appropriate database export or backup.

## Current implementation status

The local-model foundation is implemented: native PHP JSON-artifact inference, `rules`/`shadow`/`model` mode configuration, append-only sentiment runs, private annotations, admin review endpoints, and a development-only scikit-learn export script. The default remains `shadow`; promotion requires the evaluation gate above and can be rolled back with `SENTIMENT_CLASSIFIER_MODE=rules`.

Since 2026-10-30 the hybrid adapter can use a pretrained multilingual XLM-RoBERTa checkpoint (`cardiffnlp/twitter-xlm-roberta-base-sentiment`) instead of the local artifact. `SENTIMENT_MODEL_DRIVER=xlmr` selects `App\Services\XlmRobertaSentimentModel`, which posts the comment to `SENTIMENT_XLMR_URL` in the Hugging Face text-classification format and records the result as the `model` candidate (version `xlmr-twitter-base-v1`). Blank comments, a missing URL, timeouts, errors and unexpected payloads all leave the rules in charge. The optional Docker `sentiment` profile serves the same API locally. `casa:sentiment-predictions {dataset} {output} --driver=rules|local|xlmr` writes predictions for `scripts/evaluate_sentiment_model.py`, so the checkpoint can be compared on the locked test set once that set exists. Until it passes the gate, production stays on `rules` with the `local` driver.

Since 2026-11-01 there's a curated test set, because real feedback is still too sparse for Phase 2. `resources/sentiment/eval/curated-v1/` holds 240 fictional English, Tagalog and Taglish comments (`items.csv`), the author's draft labels for the adjudicator (`suggested.csv`), and two blank sheets for independent labellers. The [Labelling Guide](sentiment/LABELING_GUIDE.md) defines the labels. The workflow runs from the repository root:

```text
python scripts/sentiment_agreement.py resources/sentiment/eval/curated-v1
python scripts/lock_sentiment_testset.py resources/sentiment/eval/curated-v1
.\scripts\casa-docker.ps1 compose exec -T app php artisan casa:sentiment-predictions resources/sentiment/eval/curated-v1/locked-test.csv storage/app/private/c1-rules.csv --driver=rules
.\scripts\casa-docker.ps1 compose exec -T app php artisan casa:sentiment-predictions resources/sentiment/eval/curated-v1/locked-test.csv storage/app/private/c1-xlmr.csv --driver=xlmr
python scripts/evaluate_sentiment_model.py resources/sentiment/eval/curated-v1/locked-test.csv storage/app/private/c1-xlmr.csv storage/app/private/c1-xlmr.json --baseline storage/app/private/c1-rules.csv
```

The agreement script reports Cohen's kappa and pre-fills `final` wherever the two labellers agree. The lock script refuses blank finals and never overwrites `locked-test.csv`. Record its SHA-256 in the manifest. The evaluator uses only the standard library and reports per-language and per-tag results. With `--baseline` it applies the governance gate and exits 2 on failure. The xlmr run needs the `sentiment` profile running and `SENTIMENT_XLMR_URL` set. Curated results show how the model handles representative cases, but they are not evidence from real customers. Repeat the gate on redacted real feedback once enough has accumulated.

### Gate result, 2026-11-01 (provisional)

`curated-v1` was locked without human labellers: the final labels are the AI-drafted labels from `suggested.csv`, there is no kappa, and the set's SHA-256 is `b61daedfd07ac9e2b8abdb8803e59b1ddafce01d22b102570cedbfc4b0ee8753`. The labels were drafted by the same assistant that wrote the comments, so they may agree with the keyword rules more than human labels would; the rules' perfect scores on sarcasm and negative recall should be read with that in mind.

| Classifier | Accuracy | Macro-F1 | Negative recall | Taglish errors | Mixed errors | Gate |
|---|---|---|---|---|---|---|
| rules (baseline) | 0.846 | 0.839 | 1.000 | 11 | 15 | — |
| local | 0.829 | 0.827 | 0.907 | 15 | 16 | Failed |
| xlmr | 0.700 | 0.694 | 0.773 | 21 | 21 | Failed (all four checks) |

XLM-RoBERTa macro-F1 by language: English 0.739, Taglish 0.734, Tagalog 0.610 (rules 0.818, 0.857, 0.843). It scored 0.0 on the sarcasm rows but did better than the rules where the text disagrees with the stars (rating_conflict: 5 errors against 9). Decision: xlmr does not go to `shadow`; production stays on `rules` with the `local` driver. Rerun the gate on a human-labelled `curated-v2` or redacted real feedback before reconsidering.

## Recommended first implementation slice

Start with Phases 1–3: dataset governance, a small manually verified Casa Paraiso set, and a baseline evaluation of the existing classifier. This produces evidence about whether a trained model is worth the additional deployment and maintenance cost before changing production classification behavior.
