<?php

namespace App\Services;

use App\Models\Feedback;

class FeedbackSentimentUpdater
{
    public function persist(Feedback $feedback, array $analysis): Feedback
    {
        $feedback->forceFill([
            'sentiment_label' => $analysis['label'],
            'sentiment_score' => $analysis['score'],
            'sentiment_analysis_version' => $analysis['version'],
            'sentiment_evidence' => $analysis['evidence'],
            'sentiment_source' => $analysis['source'] ?? 'rules',
            'sentiment_confidence' => $analysis['confidence'] ?? null,
        ])->save();

        $feedback->topics()->delete();
        $feedback->topics()->createMany(array_map(
            fn (array $topic): array => [
                'topic_key' => $topic['key'],
                'polarity' => $topic['polarity'],
                'matched_terms' => $topic['matched_terms'],
            ],
            $analysis['topics'] ?? [],
        ));

        $candidates = array_values(array_filter([
            ['source' => 'rules', 'version' => $analysis['rules_candidate']['version'] ?? $analysis['version'], 'label' => $analysis['rules_candidate']['label'] ?? $analysis['label'], 'score' => $analysis['rules_candidate']['score'] ?? $analysis['score'], 'confidence' => $analysis['source'] === 'rules' ? ($analysis['confidence'] ?? 1.0) : null, 'evidence' => $analysis['rules_candidate']['evidence'] ?? $analysis['evidence'], 'is_authoritative' => ($analysis['source'] ?? 'rules') === 'rules'],
            $analysis['model_candidate'] ? ['source' => 'model', 'version' => $analysis['model_candidate']['version'], 'label' => $analysis['model_candidate']['label'], 'score' => $analysis['model_candidate']['score'], 'confidence' => $analysis['model_candidate']['confidence'], 'evidence' => $analysis['model_candidate']['evidence'], 'is_authoritative' => ($analysis['source'] ?? 'rules') === 'model'] : null,
        ]));
        $feedback->sentimentRuns()->createMany(array_map(fn (array $candidate): array => [
            'source' => $candidate['source'],
            'classifier_version' => $candidate['version'],
            'label' => $candidate['label'],
            'score' => $candidate['score'],
            'confidence' => $candidate['confidence'],
            'evidence' => $candidate['evidence'],
            'is_authoritative' => $candidate['is_authoritative'],
        ], $candidates));

        return $feedback->load('topics');
    }
}
