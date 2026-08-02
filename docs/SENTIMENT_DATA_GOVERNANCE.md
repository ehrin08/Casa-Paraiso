# Local sentiment model governance

Training is offline only. Customer comments stay in the private `casa` schema and are never sent to a hosted AI provider or copied into a public dataset.

- Redact names, phone numbers, email addresses, booking numbers, addresses, and URLs before annotation/export.
- Keep the dataset manifest, license evidence, label guide, split assignment, and model checksum with each training run.
- Use two internal reviewers for the seed set; adjudicate disagreements and reserve a locked test split.
- The Python training script is a development tool only. Production loads the exported JSON artifact through native PHP inference.
- Promote a model only when macro-F1 is at least 0.80, negative recall is within five percentage points of rules, and Taglish/mixed-polarity errors improve.
- Roll back by setting `SENTIMENT_CLASSIFIER_MODE=rules`; previous sentiment runs remain append-only for audit.
