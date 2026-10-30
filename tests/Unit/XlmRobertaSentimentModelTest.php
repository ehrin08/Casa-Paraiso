<?php

namespace Tests\Unit;

use App\Services\XlmRobertaSentimentModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XlmRobertaSentimentModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Config::set('sentiment.xlmr.url', 'https://inference.test/sentiment');
        Config::set('sentiment.xlmr.token', null);
    }

    public function test_it_maps_nested_named_labels_to_a_sentiment_candidate(): void
    {
        Http::fake(['inference.test/*' => Http::response([[
            ['label' => 'positive', 'score' => 0.9],
            ['label' => 'neutral', 'score' => 0.07],
            ['label' => 'negative', 'score' => 0.03],
        ]])]);

        $result = app(XlmRobertaSentimentModel::class)->classify(5, 'Sulit na sulit, babalik ako!');

        $this->assertSame('positive', $result['label']);
        $this->assertSame(0.87, $result['score']);
        $this->assertSame(0.9, $result['confidence']);
        $this->assertSame('xlmr-twitter-base-v1', $result['version']);
        $this->assertSame('cardiffnlp/twitter-xlm-roberta-base-sentiment', $result['evidence']['checkpoint']);
        Http::assertSent(fn (Request $request): bool => $request['inputs'] === 'Sulit na sulit, babalik ako!'
            && ! $request->hasHeader('Authorization'));
    }

    public function test_it_accepts_flat_generic_labels_and_sends_the_token(): void
    {
        Config::set('sentiment.xlmr.token', 'test-token');
        Http::fake(['inference.test/*' => Http::response([
            ['label' => 'LABEL_0', 'score' => 0.8],
            ['label' => 'LABEL_1', 'score' => 0.15],
            ['label' => 'LABEL_2', 'score' => 0.05],
        ])]);

        $result = app(XlmRobertaSentimentModel::class)->classify(1, 'Ang tagal ng hintay.');

        $this->assertSame('negative', $result['label']);
        $this->assertSame(-0.75, $result['score']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_it_skips_the_endpoint_without_a_url_or_comment(): void
    {
        Http::fake();

        $this->assertNull(app(XlmRobertaSentimentModel::class)->classify(4, '   '));
        Config::set('sentiment.xlmr.url', null);
        $this->assertNull(app(XlmRobertaSentimentModel::class)->classify(4, 'Malinis ang kwarto.'));
        Http::assertNothingSent();
    }

    public function test_failures_and_unexpected_payloads_return_null(): void
    {
        Http::fakeSequence('inference.test/*')
            ->push(['error' => 'loading'], 503)
            ->push(['unexpected' => true])
            ->push([['label' => 'joy', 'score' => 1.0]])
            ->pushFailedConnection();

        $model = app(XlmRobertaSentimentModel::class);
        foreach (range(1, 4) as $attempt) {
            $this->assertNull($model->classify(3, 'Okay naman.'), "Attempt {$attempt} should fall back.");
        }
    }

    public function test_connection_exceptions_return_null(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertNull(app(XlmRobertaSentimentModel::class)->classify(3, 'Okay naman.'));
    }
}
