<?php

namespace Tests\Unit;

use App\Services\HybridSentimentClassifier;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HybridSentimentClassifierTest extends TestCase
{
    public function test_shadow_mode_keeps_rules_authoritative_and_records_model_candidate(): void
    {
        Config::set('sentiment.mode', 'shadow');
        $result = app(HybridSentimentClassifier::class)->classify(5, 'Napakaganda at mabait ang therapist.');

        $this->assertSame('rules', $result['source']);
        $this->assertNotNull($result['model_candidate']);
        $this->assertSame($result['rules_candidate']['label'], $result['label']);
    }

    public function test_model_mode_falls_back_when_threshold_is_unreachable(): void
    {
        Config::set('sentiment.mode', 'model');
        Config::set('sentiment.model_threshold', 1.01);
        $result = app(HybridSentimentClassifier::class)->classify(5, 'The room was clean.');

        $this->assertSame('rules', $result['source']);
        $this->assertSame($result['rules_candidate']['label'], $result['label']);
    }

    public function test_xlmr_driver_in_shadow_mode_records_the_candidate_without_changing_the_label(): void
    {
        $this->fakeXlmr('negative', 0.95);
        Config::set('sentiment.mode', 'shadow');

        $result = app(HybridSentimentClassifier::class)->classify(5, 'Napakaganda at mabait ang therapist.');

        $this->assertSame('rules', $result['source']);
        $this->assertSame('positive', $result['label']);
        $this->assertSame('xlmr-twitter-base-v1', $result['model_candidate']['version']);
        $this->assertTrue($result['disagreement']);
        $this->assertSame('needs_review', $result['review_state']);
    }

    public function test_xlmr_driver_in_model_mode_selects_a_confident_prediction(): void
    {
        $this->fakeXlmr('negative', 0.95);
        Config::set('sentiment.mode', 'model');

        $result = app(HybridSentimentClassifier::class)->classify(4, 'The room was clean but the wait was long.');

        $this->assertSame('model', $result['source']);
        $this->assertSame('negative', $result['label']);
        $this->assertSame(0.95, $result['confidence']);
        $this->assertSame('xlmr-twitter-base-v1', $result['version']);
    }

    public function test_xlmr_endpoint_failure_falls_back_to_the_rules(): void
    {
        Http::preventStrayRequests();
        Http::fake(['inference.test/*' => Http::response([], 500)]);
        Config::set('sentiment.model_driver', 'xlmr');
        Config::set('sentiment.xlmr.url', 'https://inference.test/sentiment');
        Config::set('sentiment.mode', 'model');

        $result = app(HybridSentimentClassifier::class)->classify(5, 'Napakaganda at mabait ang therapist.');

        $this->assertSame('rules', $result['source']);
        $this->assertNull($result['model_candidate']);
        $this->assertFalse($result['disagreement']);
    }

    private function fakeXlmr(string $label, float $score): void
    {
        Http::preventStrayRequests();
        $rest = (1 - $score) / 2;
        Http::fake(['inference.test/*' => Http::response([array_map(
            fn (string $name): array => ['label' => $name, 'score' => $name === $label ? $score : $rest],
            ['positive', 'neutral', 'negative'],
        )])]);
        Config::set('sentiment.model_driver', 'xlmr');
        Config::set('sentiment.xlmr.url', 'https://inference.test/sentiment');
    }
}
