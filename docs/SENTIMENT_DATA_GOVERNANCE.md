# Local sentiment model governance

Training is offline only. Customer comments stay in the private `casa` schema and are never copied into a public dataset. The one hosted AI provider allowed is Google's Gemini API, under the rules below.

- Redact names, phone numbers, email addresses, booking numbers, addresses, and URLs before annotation/export.
- Keep the dataset manifest, license evidence, label guide, split assignment, and model checksum with each training run.
- Use two internal reviewers for the seed set; adjudicate disagreements and reserve a locked test split.
- The Python training script is a development tool only. Production loads the exported JSON artifact through native PHP inference.
- Promote a model only when macro-F1 is at least 0.80, negative recall is within five percentage points of rules, and Taglish/mixed-polarity errors improve.
- Roll back by setting `SENTIMENT_CLASSIFIER_MODE=rules`; previous sentiment runs remain append-only for audit.

## Gemini API (since 2026-11-01)

- **What is sent:** `SENTIMENT_MODEL_DRIVER=gemini` sends each comment, with its star rating, to the Gemini API. `App\Support\SentimentRedactor` removes email addresses, phone numbers, URLs and booking numbers first. It cannot detect names.
- **Tier:** the owner chose the free (unpaid) tier for production comments. Google's terms for unpaid services allow submitted content to be used to improve Google's products and to be read by human reviewers. They also say not to submit personal information. The [privacy policy](../resources/views/privacy-policy.blade.php) discloses this and asks customers to leave personal details out. Switching the Google Cloud project to paid billing stops this use with no code change, and is recommended.
- **Key handling:** the API key lives only in `GEMINI_API_KEY` in `.env` or the Hostinger environment. It is sent as the `x-goog-api-key` header, never in a URL, a log or a document. Logs record only status codes and exception classes, never comment text.
- **Fallback:** errors, timeouts, rate limits (HTTP 429), malformed answers and answers below `SENTIMENT_MODEL_THRESHOLD` all leave the rules in charge.
- **Gate:** it passed on the locked `curated-v2` set on 2026-11-01. See the [implementation plan](SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md). Re-run the gate whenever `SENTIMENT_GEMINI_MODEL` changes.
