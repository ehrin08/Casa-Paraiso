<?php

namespace App\Models;

use Database\Factories\RfmSegmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RfmSegment extends Model
{
    /** @use HasFactory<RfmSegmentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'preset_key',
        'priority',
        'description',
        'addon_code',
        'recommended_membership_tier_id',
        'recency_min_days',
        'recency_max_days',
        'frequency_min',
        'frequency_max',
        'monetary_min',
        'monetary_max',
        'r_score_min',
        'r_score_max',
        'f_score_min',
        'f_score_max',
        'm_score_min',
        'm_score_max',
        'is_active',
    ];

    public const METRICS = ['r' => 'Recency', 'f' => 'Frequency', 'm' => 'Monetary'];

    protected function casts(): array
    {
        return [
            'recency_min_days' => 'integer',
            'recency_max_days' => 'integer',
            'frequency_min' => 'integer',
            'frequency_max' => 'integer',
            'monetary_min' => 'decimal:2',
            'monetary_max' => 'decimal:2',
            'priority' => 'integer',
            'r_score_min' => 'integer',
            'r_score_max' => 'integer',
            'f_score_min' => 'integer',
            'f_score_max' => 'integer',
            'm_score_min' => 'integer',
            'm_score_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    public function promotionRules()
    {
        return $this->hasMany(PromotionRule::class);
    }

    public function recommendedMembershipTier()
    {
        return $this->belongsTo(MembershipTier::class, 'recommended_membership_tier_id');
    }

    public function promotionSuggestions()
    {
        return $this->hasMany(PromotionSuggestion::class);
    }

    public function addonName(): ?string
    {
        return $this->addon_code ? Addon::query()->where('code', $this->addon_code)->value('name') : null;
    }

    /** @param array{r: int, f: int, m: int} $scores */
    public function matchesScores(array $scores): bool
    {
        foreach (array_keys(self::METRICS) as $metric) {
            $min = $this->{"{$metric}_score_min"};
            $max = $this->{"{$metric}_score_max"};

            if (($min !== null && $scores[$metric] < $min) || ($max !== null && $scores[$metric] > $max)) {
                return false;
            }
        }

        return true;
    }

    public function conditionSummary(): string
    {
        return collect(array_keys(self::METRICS))->map(function (string $metric): string {
            $min = $this->{"{$metric}_score_min"};
            $max = $this->{"{$metric}_score_max"};
            $label = strtoupper($metric);

            if ($min === null && $max === null) {
                return "{$label} any";
            }

            [$low, $high] = [$min ?? 1, $max ?? 5];

            return $low === $high ? "{$label} {$low}" : "{$label} {$low}–{$high}";
        })->implode(' · ');
    }
}
