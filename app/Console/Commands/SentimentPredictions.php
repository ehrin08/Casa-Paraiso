<?php

namespace App\Console\Commands;

use App\Services\LocalSentimentModel;
use App\Services\SentimentClassifier;
use App\Services\XlmRobertaSentimentModel;
use Illuminate\Console\Command;

class SentimentPredictions extends Command
{
    protected $signature = 'casa:sentiment-predictions
        {dataset : CSV with text, rating and label columns}
        {output : Predictions CSV for scripts/evaluate_sentiment_model.py}
        {--driver=rules : rules, local or xlmr}';

    protected $description = 'Run one sentiment classifier over a labelled dataset and write its predictions for offline evaluation.';

    public function handle(SentimentClassifier $rules, LocalSentimentModel $local, XlmRobertaSentimentModel $xlmr): int
    {
        $driver = (string) $this->option('driver');
        if (! in_array($driver, ['rules', 'local', 'xlmr'], true)) {
            $this->error('The driver must be rules, local or xlmr.');

            return self::FAILURE;
        }

        $input = @fopen((string) $this->argument('dataset'), 'r');
        if ($input === false) {
            $this->error('The dataset could not be opened.');

            return self::FAILURE;
        }

        $header = fgetcsv($input, escape: '');
        $columns = is_array($header) ? array_flip(array_map(fn ($name): string => trim((string) $name, "\u{FEFF} "), $header)) : [];
        if (! isset($columns['text'], $columns['rating'])) {
            fclose($input);
            $this->error('The dataset needs text and rating columns.');

            return self::FAILURE;
        }

        $output = fopen((string) $this->argument('output'), 'w');
        fputcsv($output, ['label', 'confidence'], escape: '');
        $rows = 0;
        $unavailable = 0;
        while (($row = fgetcsv($input, escape: '')) !== false) {
            $rating = (int) ($row[$columns['rating']] ?? 0);
            $text = (string) ($row[$columns['text']] ?? '');
            $prediction = match ($driver) {
                'rules' => $rules->classify($rating, $text),
                'local' => $local->classify($rating, $text),
                'xlmr' => $xlmr->classify($rating, $text),
            };
            if ($prediction === null) {
                // Rows the model cannot score fall back to the rules, as in production.
                $unavailable++;
                $prediction = $rules->classify($rating, $text);
            }
            fputcsv($output, [$prediction['label'], $prediction['confidence'] ?? ''], escape: '');
            $rows++;
        }
        fclose($input);
        fclose($output);

        $this->info("Rows predicted with {$driver}: {$rows}");
        if ($unavailable > 0) {
            $this->warn("Rows that fell back to the rules: {$unavailable}");
        }

        return self::SUCCESS;
    }
}
