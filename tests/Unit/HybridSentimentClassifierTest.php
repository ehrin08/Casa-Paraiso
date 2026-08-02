<?php

namespace Tests\Unit;

use App\Services\HybridSentimentClassifier;
use Illuminate\Support\Facades\Config;
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
}
