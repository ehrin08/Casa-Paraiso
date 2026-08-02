<?php

namespace Tests\Unit;

use App\Models\Feedback;
use App\Services\SentimentClassifier;
use Tests\TestCase;

class SentimentClassifierTest extends TestCase
{
    public function test_it_classifies_english_tagalog_and_taglish_comments(): void
    {
        $classifier = app(SentimentClassifier::class);

        foreach ([
            [5, 'Badminton was fun.', Feedback::SENTIMENT_POSITIVE],
            [5, 'The room was not very clean.', Feedback::SENTIMENT_NEGATIVE],
            [3, 'The service was not bad.', Feedback::SENTIMENT_POSITIVE],
            [5, 'Great treatment but rude checkout.', Feedback::SENTIMENT_NEUTRAL],
            [5, 'Napakaganda ng serbisyo at mabait ang therapist.', Feedback::SENTIMENT_POSITIVE],
            [5, 'Marumi ang kwarto at sobrang tagal ng hintay.', Feedback::SENTIMENT_NEGATIVE],
            [3, 'Hindi masama ang masahe.', Feedback::SENTIMENT_POSITIVE],
            [5, 'Hindi malinis ang kwarto.', Feedback::SENTIMENT_NEGATIVE],
            [3, 'Walang sakit at nakakarelax.', Feedback::SENTIMENT_POSITIVE],
            [5, '’Di maganda ang serbisyo.', Feedback::SENTIMENT_NEGATIVE],
            [5, 'Relaxing ang massage pero rude ang checkout.', Feedback::SENTIMENT_NEUTRAL],
            [5, 'Never again. Clean room.', Feedback::SENTIMENT_NEUTRAL],
            [5, 'Walang reklamo, sulit na sulit.', Feedback::SENTIMENT_POSITIVE],
            [2, 'Maganda naman ang place.', Feedback::SENTIMENT_NEGATIVE],
            [4, 'Unrecognized wording only.', Feedback::SENTIMENT_POSITIVE],
            [1, null, Feedback::SENTIMENT_NEGATIVE],
        ] as [$rating, $comment, $expected]) {
            $result = $classifier->classify($rating, $comment);

            $this->assertSame($expected, $result['label'], $comment ?? 'No comment');
            $this->assertSame(match ($expected) {
                Feedback::SENTIMENT_POSITIVE => 1.0,
                Feedback::SENTIMENT_NEGATIVE => -1.0,
                default => 0.0,
            }, $result['score'], $comment ?? 'No comment');
        }
    }

    public function test_it_returns_versioned_explainable_topic_findings(): void
    {
        $result = app(SentimentClassifier::class)->classify(5, 'The room was not clean, the therapist was kind, but the wait was late.');

        $this->assertSame(SentimentClassifier::ANALYSIS_VERSION, $result['version']);
        $this->assertSame('negative', $result['label']);
        $this->assertSame('negative', collect($result['topics'])->firstWhere('key', 'cleanliness_ambience')['polarity']);
        $this->assertSame('positive', collect($result['topics'])->firstWhere('key', 'therapist_service')['polarity']);
        $this->assertSame(3, $result['evidence']['matched_words']);
    }
}
