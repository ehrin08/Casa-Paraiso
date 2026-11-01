<?php

namespace App\Services;

use App\Models\Feedback;
use App\Support\SentimentRedactor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiSentimentModel
{
    private const LABELS = [Feedback::SENTIMENT_POSITIVE, Feedback::SENTIMENT_NEUTRAL, Feedback::SENTIMENT_NEGATIVE];

    // Condensed from docs/sentiment/LABELING_GUIDE.md, plus the star-rating blend the stored label uses.
    private const INSTRUCTION = <<<'TEXT'
        You label spa customer feedback for Casa Paraiso in the Philippines. Comments are in English, Tagalog or Taglish and may contain misspellings.
        Return one label for the whole visit: positive, neutral or negative.
        - positive: the customer is mainly satisfied (praise, intent to return, relief from pain).
        - negative: the customer is mainly dissatisfied (complaints, intent not to return, sarcasm about a bad experience).
        - neutral: factual statements, questions, suggestions, or praise and complaints that balance out; lukewarm wording such as "okay lang" or "just average".
        - In mixed comments, the clause after "but" or "pero" usually carries the verdict; hedges like "medyo" or "a bit" soften a complaint.
        - Read negation as meaning: "hindi masakit" and "didn't hurt" are praise; "not worth it" is a complaint.
        - Sarcasm ("Wow, one hour lang naman ako naghintay") is negative.
        The star rating is supplied as context. A comment with a clear opinion decides the label, even when it says the stars were a mistake. Use the stars only when the comment states no opinion of its own.
        Set confidence between 0 and 1 for how sure you are of the label.
        TEXT;

    public function available(): bool
    {
        return filled(config('sentiment.gemini.key'));
    }

    /** @return array{label:string,score:float,confidence:float,version:string,evidence:array<string,mixed>}|null */
    public function classify(int $rating, ?string $comment): ?array
    {
        $text = SentimentRedactor::redact((string) $comment);
        if (! $this->available() || $text === '') {
            return null;
        }

        $model = (string) config('sentiment.gemini.model');

        try {
            // The key travels in a header so it never appears in logged URLs.
            $response = Http::acceptJson()
                ->timeout((float) config('sentiment.gemini.timeout', 5))
                ->withHeaders(['x-goog-api-key' => (string) config('sentiment.gemini.key')])
                ->post(rtrim((string) config('sentiment.gemini.base_url'), '/')."/models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => self::INSTRUCTION]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => "Rating: {$rating}/5\nComment: ".mb_substr($text, 0, (int) config('sentiment.gemini.max_chars', 1000))]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'label' => ['type' => 'STRING', 'enum' => self::LABELS],
                                'confidence' => ['type' => 'NUMBER'],
                            ],
                            'required' => ['label', 'confidence'],
                            'propertyOrdering' => ['label', 'confidence'],
                        ],
                    ],
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Gemini sentiment endpoint unreachable.', ['exception' => $exception::class]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Gemini sentiment endpoint failed.', ['status' => $response->status()]);

            return null;
        }

        $answer = json_decode((string) $response->json('candidates.0.content.parts.0.text'), true);
        $label = is_array($answer) ? ($answer['label'] ?? null) : null;
        if (! in_array($label, self::LABELS, true) || ! is_numeric($answer['confidence'] ?? null)) {
            Log::warning('Gemini sentiment endpoint returned an unexpected payload.');

            return null;
        }

        $confidence = round(max(0.0, min(1.0, (float) $answer['confidence'])), 4);

        return [
            'label' => $label,
            'score' => round(match ($label) {
                Feedback::SENTIMENT_POSITIVE => $confidence,
                Feedback::SENTIMENT_NEGATIVE => -$confidence,
                default => 0.0,
            }, 2),
            'confidence' => $confidence,
            'version' => (string) config('sentiment.gemini.version'),
            'evidence' => ['model' => $model, 'rating_sent' => true],
        ];
    }
}
