<?php

namespace App\Services;

use App\Models\Feedback;
use RuntimeException;

class LocalSentimentModel
{
    private ?array $artifact = null;

    public function available(): bool
    {
        return is_file((string) config('sentiment.model_path'));
    }

    /** @return array{label:string,score:float,confidence:float,version:string,evidence:array<string,mixed>}|null */
    public function classify(int $rating, ?string $comment): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $artifact = $this->artifact();
        $features = $this->features($rating, $comment);
        $scores = array_fill(0, count($artifact['labels']), 0.0);
        foreach ($artifact['bias'] as $index => $bias) {
            $scores[$index] = (float) $bias;
        }
        foreach ($features as $feature => $value) {
            foreach (($artifact['weights'][$feature] ?? []) as $index => $weight) {
                $scores[$index] += (float) $weight * $value;
            }
        }

        $probabilities = $this->softmax($scores);
        arsort($probabilities);
        $winner = (int) array_key_first($probabilities);
        $confidence = (float) $probabilities[$winner];
        $label = $artifact['labels'][$winner];

        return [
            'label' => $label,
            'score' => match ($label) {
                Feedback::SENTIMENT_POSITIVE => 1.0,
                Feedback::SENTIMENT_NEGATIVE => -1.0,
                default => 0.0,
            },
            'confidence' => round($confidence, 4),
            'version' => (string) $artifact['version'],
            'evidence' => [
                'features' => array_keys($features),
                'probabilities' => array_combine($artifact['labels'], array_map(fn ($value): float => round((float) $value, 4), $this->softmax($scores))),
            ],
        ];
    }

    private function artifact(): array
    {
        if ($this->artifact !== null) {
            return $this->artifact;
        }

        $decoded = json_decode((string) file_get_contents((string) config('sentiment.model_path')), true);
        if (! is_array($decoded) || ! isset($decoded['labels'], $decoded['bias'], $decoded['weights'])) {
            throw new RuntimeException('Invalid sentiment model artifact.');
        }

        return $this->artifact = $decoded;
    }

    private function features(int $rating, ?string $comment): array
    {
        $normalized = mb_strtolower((string) $comment);
        preg_match_all('/[\p{L}\p{N}\']+/u', $normalized, $matches);
        $features = [];
        foreach ($matches[0] ?? [] as $token) {
            $features[$token] = ($features[$token] ?? 0) + 1;
            $features['word::'.$token] = 1;
        }
        $tokens = $matches[0] ?? [];
        for ($index = 0; $index < count($tokens) - 1; $index++) {
            $features['word::'.$tokens[$index].' '.$tokens[$index + 1]] = 1;
        }
        for ($length = 3; $length <= 5; $length++) {
            for ($index = 0; $index <= mb_strlen($normalized) - $length; $index++) {
                $features['char::'.mb_substr($normalized, $index, $length)] = 1;
            }
        }
        $features['rating_'.$rating] = 1;

        return $features;
    }

    private function softmax(array $scores): array
    {
        $max = max($scores);
        $exponentials = array_map(fn ($score): float => exp((float) $score - $max), $scores);
        $sum = array_sum($exponentials) ?: 1.0;

        return array_map(fn ($value): float => $value / $sum, $exponentials);
    }
}
