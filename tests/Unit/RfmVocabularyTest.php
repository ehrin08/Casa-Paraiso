<?php

namespace Tests\Unit;

use App\Models\RfmSegment;
use App\Services\RfmVocabulary;
use Tests\TestCase;

class RfmVocabularyTest extends TestCase
{
    public function test_rules_read_as_one_plain_sentence(): void
    {
        $words = app(RfmVocabulary::class);
        $highValue = new RfmSegment(['r_score_min' => 4, 'r_score_max' => 5, 'f_score_min' => 4, 'f_score_max' => 5, 'm_score_min' => 4, 'm_score_max' => 5]);

        $this->assertSame(
            'Customers who last visited recently or very recently, come often or very often and spend a lot or the most get a free VIP Room and are recommended the Platinum card.',
            $words->sentence($highValue, 'VIP Room', 'Platinum'),
        );
        $this->assertSame('Customers who last visited very long ago get a free Hot Stone.', $words->sentence(new RfmSegment(['r_score_max' => 1]), 'Hot Stone'));
        $this->assertSame('Customers who come anywhere from regularly to very often are grouped here without a reward.', $words->sentence(new RfmSegment(['f_score_min' => 3])));
        $this->assertSame('All customers with paid visits are grouped here without a reward.', $words->sentence(new RfmSegment(['r_score_min' => 1, 'r_score_max' => 5])));
    }

    public function test_levels_and_value_hints_replace_score_numbers(): void
    {
        $words = app(RfmVocabulary::class);
        $cutoffs = ['r' => [4 => ['min' => 4, 'max' => 12]], 'f' => [1 => ['min' => 1, 'max' => 1]], 'm' => [5 => ['min' => 8000.0, 'max' => 15500.5]]];

        $this->assertSame(['Last visit', 'How often', 'Total spent'], array_values($words->metrics()));
        $this->assertSame('Recent', $words->level('r', 4));
        $this->assertSame('Top spenders', $words->level('m', 5));
        $this->assertSame('4 days – 12 days', $words->valueHint('r', 4, $cutoffs));
        $this->assertSame('1 visit', $words->valueHint('f', 1, $cutoffs));
        $this->assertSame('₱8,000 – ₱15,500.50', $words->valueHint('m', 5, $cutoffs));
        $this->assertNull($words->valueHint('r', 1, $cutoffs));
    }
}
