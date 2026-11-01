<?php

namespace App\Services;

use App\Models\RfmSegment;

/**
 * Plain-language wording for RFM scores, so admins never have to read 1–5 numbers.
 * Display only: scores, rules and matching stay in RfmScorer and RfmRuleEngine.
 */
class RfmVocabulary
{
    /** @return array<string, string> */
    public function metrics(): array
    {
        return ['r' => __('Last visit'), 'f' => __('How often'), 'm' => __('Total spent')];
    }

    public function metric(string $metric): string
    {
        return $this->metrics()[$metric];
    }

    /** @return array<int, string> Level name for each score, lowest score first. */
    public function levels(string $metric): array
    {
        return match ($metric) {
            'r' => [1 => __('Very long ago'), 2 => __('Long ago'), 3 => __('A while ago'), 4 => __('Recent'), 5 => __('Very recent')],
            'f' => [1 => __('Rarely'), 2 => __('Occasionally'), 3 => __('Regularly'), 4 => __('Often'), 5 => __('Very often')],
            'm' => [1 => __('Lowest'), 2 => __('Low'), 3 => __('Average'), 4 => __('High'), 5 => __('Top spenders')],
        };
    }

    public function level(string $metric, int $score): string
    {
        return $this->levels($metric)[$score];
    }

    /** Today's real values behind a score, e.g. "4 – 12 days", or null when no customer has it. */
    public function valueHint(string $metric, int $score, array $cutoffs): ?string
    {
        $range = $cutoffs[$metric][$score] ?? null;

        if ($range === null) {
            return null;
        }

        return $range['min'] == $range['max']
            ? $this->formatValue($metric, $range['min'])
            : $this->formatValue($metric, $range['min']).' – '.$this->formatValue($metric, $range['max']);
    }

    public function formatValue(string $metric, int|float $value): string
    {
        return match ($metric) {
            'r' => trans_choice(':count day|:count days', (int) $value),
            'f' => trans_choice(':count visit|:count visits', (int) $value),
            default => '₱'.number_format((float) $value, fmod((float) $value, 1) === 0.0 ? 0 : 2),
        };
    }

    /**
     * Ready-made starting points for a new group. They only fill the form; the admin still saves.
     *
     * @return array<string, array{name: string, description: string, r_score_min: ?int, r_score_max: ?int, f_score_min: ?int, f_score_max: ?int, m_score_min: ?int, m_score_max: ?int}>
     */
    public function templates(): array
    {
        $ranges = fn (array $r, array $f, array $m = [null, null]) => [
            'r_score_min' => $r[0], 'r_score_max' => $r[1],
            'f_score_min' => $f[0], 'f_score_max' => $f[1],
            'm_score_min' => $m[0], 'm_score_max' => $m[1],
        ];

        return [
            'loyal-regulars' => ['name' => __('Reward loyal regulars'), 'description' => __('Customers who come often and still visit fairly recently.'), ...$ranges([3, 5], [4, 5])],
            'win-back' => ['name' => __('Win back lost customers'), 'description' => __('Customers who used to come regularly but have not been back for a long time.'), ...$ranges([1, 2], [3, 5])],
            'welcome-new' => ['name' => __('Welcome new customers'), 'description' => __('Customers who visited recently for the first time.'), ...$ranges([4, 5], [1, 1])],
        ];
    }

    /** The whole rule as one sentence, e.g. "Customers who last visited recently or very recently … get a free VIP Room." */
    public function sentence(RfmSegment $segment, ?string $addonName = null, ?string $tierName = null): string
    {
        $conditions = collect(array_keys(RfmSegment::METRICS))
            ->map(fn (string $metric) => $this->phrase($metric, $segment->{"{$metric}_score_min"}, $segment->{"{$metric}_score_max"}))
            ->filter()
            ->values();

        $who = $conditions->isEmpty()
            ? __('All customers with paid visits')
            : __('Customers who :conditions', ['conditions' => $conditions->count() > 1
                ? $conditions->slice(0, -1)->implode(', ').' '.__('and').' '.$conditions->last()
                : $conditions->first()]);

        $outcome = $addonName ? __('get a free :reward', ['reward' => $addonName]) : __('are grouped here without a reward');

        if ($tierName) {
            $outcome .= ' '.__('and are recommended the :tier card', ['tier' => $tierName]);
        }

        return "{$who} {$outcome}.";
    }

    /** One measure's range in words, or null when the rule ignores it. */
    public function phrase(string $metric, ?int $min, ?int $max): ?string
    {
        [$low, $high] = [$min ?? 1, $max ?? 5];

        if ($low === 1 && $high === 5) {
            return null;
        }

        $words = match ($metric) {
            'r' => [1 => __('very long ago'), 2 => __('long ago'), 3 => __('a while ago'), 4 => __('recently'), 5 => __('very recently')],
            'f' => [1 => __('rarely'), 2 => __('occasionally'), 3 => __('regularly'), 4 => __('often'), 5 => __('very often')],
            'm' => [1 => __('the least'), 2 => __('a little'), 3 => __('an average amount'), 4 => __('a lot'), 5 => __('the most')],
        };

        $range = match ($high - $low) {
            0 => $words[$low],
            1 => __(':low or :high', ['low' => $words[$low], 'high' => $words[$high]]),
            default => __('anywhere from :low to :high', ['low' => $words[$low], 'high' => $words[$high]]),
        };

        return match ($metric) {
            'r' => __('last visited :range', ['range' => $range]),
            'f' => __('come :range', ['range' => $range]),
            'm' => __('spend :range', ['range' => $range]),
        };
    }
}
