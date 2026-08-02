<?php

namespace App\Models;

use App\Models\Addon;

use Database\Factories\PromotionRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromotionRule extends Model
{
    /** @use HasFactory<PromotionRuleFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rfm_segment_id',
        'name',
        'description',
        'suggested_offer',
        'addon_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function rfmSegment()
    {
        return $this->belongsTo(RfmSegment::class);
    }

    public function promotionSuggestions()
    {
        return $this->hasMany(PromotionSuggestion::class);
    }

    public function addonName(): ?string
    {
        return $this->addon_code ? Addon::query()->where('code', $this->addon_code)->value('name') : null;
    }
}
