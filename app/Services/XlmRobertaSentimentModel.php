<?php

namespace App\Services;

use App\Models\Feedback;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XlmRobertaSentimentModel
{
    private const LABELS = [
        'negative' => Feedback::SENTIMENT_NEGATIVE,
        'neutral' => Feedback::SENTIMENT_NEUTRAL,
        'positive' => Feedback::SENTIMENT_POSITIVE,
        'label_0' => Feedback::SENTIMENT_NEGATIVE,
        'label_1' => Feedback::SENTIMENT_NEUTRAL,
        'label_2' => Feedback::SENTIMENT_POSITIVE,
    ];

    public function available(): bool
    {
        return filled(config('sentiment.xlmr.url'));
    }

    /** @return array{label:string,score:float,confidence:float,version:string,evidence:array<string,mixed>}|null */
    public function classify(int $rating, ?string $comment): ?array
    {
        $text = trim((string) $comment);
        if (! $this->available() || $text === '') {
            return null;
        }

        try {
            $request = Http::acceptJson()->timeout((float) config('sentiment.xlmr.timeout', 3));
            if (filled(config('sentiment.xlmr.token'))) {
                $request = $request->withToken((string) config('sentiment.xlmr.token'));
            }
            $response = $request->post((string) config('sentiment.xlmr.url'), [
                'inputs' => mb_substr($text, 0, (int) config('sentiment.xlmr.max_chars', 1000)),
                'options' => ['wait_for_model' => true],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('XLM-RoBERTa sentiment endpoint unreachable.', ['exception' => $exception::class]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('XLM-RoBERTa sentiment endpoint failed.', ['status' => $response->status()]);

            return null;
        }

        $probabilities = $this->probabilities($response->json());
        if ($probabilities === null) {
            Log::warning('XLM-RoBERTa sentiment endpoint returned an unexpected payload.');

            return null;
        }

        arsort($probabilities);
        $label = (string) array_key_first($probabilities);

        return [
            'label' => $label,
            'score' => round(($probabilities[Feedback::SENTIMENT_POSITIVE] ?? 0.0) - ($probabilities[Feedback::SENTIMENT_NEGATIVE] ?? 0.0), 2),
            'confidence' => round($probabilities[$label], 4),
            'version' => (string) config('sentiment.xlmr.version'),
            'evidence' => [
                'checkpoint' => (string) config('sentiment.xlmr.checkpoint'),
                'probabilities' => array_map(fn (float $value): float => round($value, 4), $probabilities),
            ],
        ];
    }

    /** Accepts the Hugging Face text-classification shapes `[[{label,score}]]` and `[{label,score}]`. */
    private function probabilities(mixed $payload): ?array
    {
        if (is_array($payload) && isset($payload[0]) && is_array($payload[0]) && array_is_list($payload[0])) {
            $payload = $payload[0];
        }
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $probabilities = [];
        foreach ($payload as $entry) {
            $label = self::LABELS[mb_strtolower((string) ($entry['label'] ?? ''))] ?? null;
            if ($label === null || ! is_numeric($entry['score'] ?? null)) {
                return null;
            }
            $probabilities[$label] = (float) $entry['score'];
        }

        return $probabilities;
    }
}
