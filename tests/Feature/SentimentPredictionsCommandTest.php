<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SentimentPredictionsCommandTest extends TestCase
{
    public function test_it_writes_xlmr_predictions_and_falls_back_to_rules_for_blank_text(): void
    {
        Http::preventStrayRequests();
        Http::fake(['inference.test/*' => Http::response([[
            ['label' => 'negative', 'score' => 0.88],
            ['label' => 'neutral', 'score' => 0.1],
            ['label' => 'positive', 'score' => 0.02],
        ]])]);
        Config::set('sentiment.xlmr.url', 'https://inference.test/sentiment');

        $dataset = tempnam(sys_get_temp_dir(), 'casa-dataset');
        $output = tempnam(sys_get_temp_dir(), 'casa-predictions');
        file_put_contents($dataset, "text,rating,label\n\"Ang tagal ng hintay, bastos pa.\",1,negative\n,5,positive\n");

        $this->artisan('casa:sentiment-predictions', ['dataset' => $dataset, 'output' => $output, '--driver' => 'xlmr'])
            ->expectsOutput('Rows predicted with xlmr: 2')
            ->expectsOutput('Rows that fell back to the rules: 1')
            ->assertSuccessful();

        $lines = array_map('str_getcsv', file($output, FILE_IGNORE_NEW_LINES));
        $this->assertSame(['label', 'confidence'], $lines[0]);
        $this->assertSame(['negative', '0.88'], $lines[1]);
        $this->assertSame('positive', $lines[2][0]);
        Http::assertSentCount(1);

        unlink($dataset);
        unlink($output);
    }

    public function test_it_writes_gemini_predictions(): void
    {
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"label":"neutral","confidence":0.7}']]]]]])]);
        Config::set('sentiment.gemini.key', 'test-gemini-key');

        $dataset = tempnam(sys_get_temp_dir(), 'casa-dataset');
        $output = tempnam(sys_get_temp_dir(), 'casa-predictions');
        file_put_contents($dataset, "text,rating,label\n\"Okay lang.\",3,neutral\n");

        $this->artisan('casa:sentiment-predictions', ['dataset' => $dataset, 'output' => $output, '--driver' => 'gemini'])
            ->expectsOutput('Rows predicted with gemini: 1')
            ->assertSuccessful();

        $this->assertSame(['neutral', '0.7'], str_getcsv(file($output, FILE_IGNORE_NEW_LINES)[1]));
        Http::assertSentCount(1);

        unlink($dataset);
        unlink($output);
    }

    public function test_it_rejects_an_unknown_driver(): void
    {
        $this->artisan('casa:sentiment-predictions', ['dataset' => 'missing.csv', 'output' => 'out.csv', '--driver' => 'gpt'])
            ->assertFailed();
    }
}
