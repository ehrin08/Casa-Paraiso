<?php

namespace Tests\Unit;

use App\Services\GeminiSentimentModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiSentimentModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Config::set('sentiment.gemini.key', 'test-gemini-key');
        Config::set('sentiment.gemini.model', 'gemini-test-model');
    }

    public function test_it_maps_a_structured_answer_to_a_sentiment_candidate(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->answer('{"label":"positive","confidence":0.92}')]);

        $result = app(GeminiSentimentModel::class)->classify(5, 'Sulit na sulit, babalik ako!');

        $this->assertSame('positive', $result['label']);
        $this->assertSame(0.92, $result['score']);
        $this->assertSame(0.92, $result['confidence']);
        $this->assertSame('gemini-v1', $result['version']);
        $this->assertSame('gemini-test-model', $result['evidence']['model']);
    }

    public function test_it_sends_the_key_as_a_header_with_the_rating_and_a_redacted_comment(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->answer('{"label":"negative","confidence":0.8}')]);

        $result = app(GeminiSentimentModel::class)->classify(1, 'Text me at 09171234567 or ana@example.com, ang tagal ng hintay.');

        $this->assertSame(-0.8, $result['score']);
        Http::assertSent(function (Request $request): bool {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent'
                && $request->header('x-goog-api-key') === ['test-gemini-key']
                && ! str_contains($request->url(), 'test-gemini-key')
                && str_starts_with($prompt, "Rating: 1/5\n")
                && str_contains($prompt, '[phone]') && str_contains($prompt, '[email]')
                && ! str_contains($prompt, '09171234567') && ! str_contains($prompt, 'ana@example.com')
                && $request['generationConfig']['responseMimeType'] === 'application/json';
        });
    }

    public function test_it_skips_the_api_without_a_key_or_comment(): void
    {
        Http::fake();

        $this->assertNull(app(GeminiSentimentModel::class)->classify(4, '   '));
        Config::set('sentiment.gemini.key', null);
        $this->assertNull(app(GeminiSentimentModel::class)->classify(4, 'Malinis ang kwarto.'));
        Http::assertNothingSent();
    }

    public function test_failures_and_unexpected_answers_return_null(): void
    {
        foreach ([
            Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429),
            Http::response([], 500),
            Http::response(['candidates' => []]),
            $this->answer('not json'),
            $this->answer('{"label":"mixed","confidence":0.9}'),
            $this->answer('{"label":"positive"}'),
        ] as $attempt => $response) {
            Http::fake(['generativelanguage.googleapis.com/*' => $response]);

            $this->assertNull(app(GeminiSentimentModel::class)->classify(3, 'Okay naman.'), "Attempt {$attempt} should fall back.");
        }
    }

    public function test_connection_exceptions_return_null(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertNull(app(GeminiSentimentModel::class)->classify(3, 'Okay naman.'));
    }

    private function answer(string $text): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]]]);
    }
}
