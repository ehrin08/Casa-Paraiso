<?php

namespace App\Console\Commands;

use App\Models\Feedback;
use Illuminate\Console\Command;

class ExportSentimentDataset extends Command
{
    protected $signature = 'casa:export-sentiment-dataset {--output= : Optional CSV path outside the public web root}';

    protected $description = 'Export a redacted, pseudonymous sentiment dataset for offline annotation or training.';

    public function handle(): int
    {
        $rows = Feedback::query()->with('topics')->orderBy('id')->get()->map(function (Feedback $feedback): array {
            return [
                hash_hmac('sha256', (string) $feedback->id, (string) config('app.key')),
                $this->redact((string) $feedback->comment),
                $feedback->rating,
                $feedback->sentiment_label,
                'unknown',
                $feedback->topics->pluck('topic_key')->implode('|'),
            ];
        });

        $this->info("Feedback records prepared: {$rows->count()}");
        $output = $this->option('output');
        if (! $output) {
            $this->comment('Dry run only. Provide --output with a protected, non-public path to write the CSV.');
            return self::SUCCESS;
        }

        $directory = dirname((string) $output);
        if (! is_dir($directory) || ! is_writable($directory)) {
            $this->error('The output directory must already exist and be writable.');
            return self::FAILURE;
        }

        $handle = fopen((string) $output, 'wb');
        fputcsv($handle, ['text_id', 'text', 'rating', 'label', 'language', 'topics']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        $this->info("Redacted dataset written to {$output}");

        return self::SUCCESS;
    }

    private function redact(string $comment): string
    {
        $comment = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', '[email]', $comment) ?? $comment;
        $comment = preg_replace('/https?:\/\/\S+/iu', '[url]', $comment) ?? $comment;
        $comment = preg_replace('/\b(?:APT|TRX)[-\s]?\w+\b/iu', '[booking]', $comment) ?? $comment;
        $comment = preg_replace('/(?<!\w)(?:\+?63|0)9\d{9}(?!\w)/', '[phone]', $comment) ?? $comment;

        return trim($comment);
    }
}
