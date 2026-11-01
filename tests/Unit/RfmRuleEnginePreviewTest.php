<?php

namespace Tests\Unit;

use App\Models\RfmSegment;
use App\Services\RfmRuleEngine;
use Tests\TestCase;

class RfmRuleEnginePreviewTest extends TestCase
{
    public function test_preview_counts_who_fits_and_who_is_left_after_rules_above(): void
    {
        $engine = app(RfmRuleEngine::class);
        $scores = collect([['r' => 5, 'f' => 5, 'm' => 5], ['r' => 5, 'f' => 1, 'm' => 2], ['r' => 1, 'f' => 3, 'm' => 3]]);
        $recent = new RfmSegment(['r_score_min' => 4, 'is_active' => true]);
        $champions = new RfmSegment(['f_score_min' => 5, 'is_active' => true]);

        $this->assertSame(['fits' => 2, 'lands' => 2], $engine->preview($recent, $scores, collect()));
        $this->assertSame(['fits' => 2, 'lands' => 1], $engine->preview($recent, $scores, collect([$champions])));
        $this->assertSame(['fits' => 3, 'lands' => 1], $engine->preview(new RfmSegment(['is_active' => true]), $scores, collect([$recent])));
    }

    public function test_inactive_rules_above_are_skipped_and_an_inactive_candidate_takes_nobody(): void
    {
        $engine = app(RfmRuleEngine::class);
        $scores = collect([['r' => 5, 'f' => 5, 'm' => 5]]);
        $everyone = new RfmSegment(['is_active' => true]);

        $this->assertSame(['fits' => 1, 'lands' => 1], $engine->preview($everyone, $scores, collect([new RfmSegment(['is_active' => false])])));
        $this->assertSame(['fits' => 1, 'lands' => 0], $engine->preview(new RfmSegment(['is_active' => false]), $scores, collect()));
    }
}
