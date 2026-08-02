<?php

namespace App\Services;

use App\Models\Feedback;

class HybridSentimentClassifier
{
    public function __construct(
        private readonly SentimentClassifier $rules,
        private readonly LocalSentimentModel $model,
    ) {
    }

    public function classify(int $rating, ?string $comment): array
    {
        $rules = $this->rules->classify($rating, $comment);
        $mode = (string) config('sentiment.mode', 'shadow');
        $model = null;

        try {
            $model = $this->model->classify($rating, $comment);
        } catch (\Throwable) {
            $model = null;
        }

        $selected = $rules;
        $source = 'rules';
        $confidence = 1.0;
        if ($mode === 'model' && $model !== null && $model['confidence'] >= (float) config('sentiment.model_threshold', 0.8)) {
            $selected = array_replace($rules, [
                'label' => $model['label'],
                'score' => $model['score'],
                'version' => $model['version'],
                'evidence' => array_merge($rules['evidence'], ['model' => $model['evidence']]),
            ]);
            $source = 'model';
            $confidence = $model['confidence'];
        }

        return array_merge($selected, [
            'source' => $source,
            'confidence' => $confidence,
            'rules_candidate' => ['label' => $rules['label'], 'score' => $rules['score'], 'version' => $rules['version'], 'evidence' => $rules['evidence']],
            'model_candidate' => $model,
            'disagreement' => $model !== null && $model['label'] !== $rules['label'],
            'review_state' => ($model !== null && ($model['confidence'] < (float) config('sentiment.model_threshold', 0.8) || $model['label'] !== $rules['label'])) ? 'needs_review' : 'unreviewed',
        ]);
    }
}
