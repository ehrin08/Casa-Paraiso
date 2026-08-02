<?php

namespace App\Models;

use Database\Factories\PromotionSuggestionFactory;
use App\Models\Addon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionSuggestion extends Model
{
    /** @use HasFactory<PromotionSuggestionFactory> */
    use HasFactory;

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUSES = [
        self::STATUS_SUGGESTED,
        self::STATUS_REVIEWED,
        self::STATUS_APPLIED,
        self::STATUS_DISMISSED,
    ];

    public const ADMIN_REVIEW_STATUSES = [
        self::STATUS_SUGGESTED,
        self::STATUS_REVIEWED,
        self::STATUS_DISMISSED,
    ];

    protected $fillable = [
        'customer_profile_id',
        'rfm_segment_id',
        'promotion_rule_id',
        'generation_key',
        'recency_days',
        'frequency_count',
        'monetary_total',
        'suggested_offer',
        'addon_code',
        'status',
        'reviewed_by',
        'reviewed_at',
        'applied_at',
        'dismissed_at',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recency_days' => 'integer',
            'frequency_count' => 'integer',
            'monetary_total' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class)->withTrashed();
    }

    public function rfmSegment()
    {
        return $this->belongsTo(RfmSegment::class);
    }

    public function promotionRule()
    {
        return $this->belongsTo(PromotionRule::class)->withTrashed();
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function addonName(): ?string
    {
        return $this->addon_code ? Addon::query()->where('code', $this->addon_code)->value('name') : null;
    }

    public function hasActiveVoucherReservation(): bool
    {
        if ($this->relationLoaded('appointments')) {
            return $this->appointments->contains('status', Appointment::STATUS_CONFIRMED);
        }

        return $this->appointments()
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->exists();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_SUGGESTED
            && $this->expires_at !== null
            && $this->expires_at->lte(now());
    }

    public function isAvailableVoucher(): bool
    {
        return $this->status === self::STATUS_SUGGESTED
            && $this->addon_code !== null
            && $this->addonName() !== null
            && Addon::query()->where('code', $this->addon_code)->where('is_active', true)->exists()
            && ! $this->isExpired();
    }

    public function lifecycle(): string
    {
        if ($this->status === self::STATUS_DISMISSED) {
            return 'dismissed';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->status === self::STATUS_APPLIED) {
            return $this->hasActiveVoucherReservation() ? 'reserved' : 'used';
        }

        return 'available';
    }
}
