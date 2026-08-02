<?php

namespace App\Console\Commands;

use App\Models\Feedback;
use App\Services\HybridSentimentClassifier;
use App\Services\FeedbackSentimentUpdater;
use Illuminate\Console\Command;

class ReclassifySentiment extends Command
{
    protected $signature = 'casa:reclassify-sentiment {--apply : Persist changed sentiment labels and scores.}';

    protected $description = 'Preview or apply the current English, Tagalog, and Taglish sentiment rules to feedback records.';

    public function handle(HybridSentimentClassifier $classifier, FeedbackSentimentUpdater $updater): int
    {
        $apply = (bool) $this->option('apply');
        $analyzed = 0;
        $changed = 0;
        $transitions = [];

        Feedback::query()
            ->orderBy('id')
            ->with('topics')
            ->chunkById(100, function ($feedback) use ($classifier, $updater, $apply, &$analyzed, &$changed, &$transitions): void {
                foreach ($feedback as $record) {
                    $analyzed++;
                    $sentiment = $classifier->classify($record->rating, $record->comment);

                    $currentTopics = $record->topics->map(fn ($topic): string => "{$topic->topic_key}:{$topic->polarity}:".implode(',', $topic->matched_terms ?? []))->sort()->values()->all();
                    $newTopics = collect($sentiment['topics'])->map(fn (array $topic): string => "{$topic['key']}:{$topic['polarity']}:".implode(',', $topic['matched_terms']))->sort()->values()->all();

                    if ($record->sentiment_label === $sentiment['label']
                        && $record->sentiment_score !== null
                        && (float) $record->sentiment_score === $sentiment['score']
                        && $record->sentiment_analysis_version === $sentiment['version']
                        && $record->sentiment_evidence === $sentiment['evidence']
                        && $currentTopics === $newTopics) {
                        continue;
                    }

                    $changed++;
                    $transition = "{$record->sentiment_label} → {$sentiment['label']}";
                    $transitions[$transition] = ($transitions[$transition] ?? 0) + 1;

                    if ($apply) {
                        $updater->persist($record, $sentiment);
                    }
                }
            });

        $this->info("Feedback records analyzed: {$analyzed}");
        $this->info($apply ? "Feedback records updated: {$changed}" : "Feedback records that would change: {$changed}");

        foreach ($transitions as $transition => $count) {
            $this->line("{$transition}: {$count}");
        }

        $this->newLine();
        $this->info($apply
            ? 'Sentiment reclassification applied.'
            : 'Dry run only. Re-run with --apply to persist the changes.');

        return self::SUCCESS;
    }
}
