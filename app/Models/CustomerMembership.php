<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerMembership extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_profile_id',
        'membership_tier_id',
        'card_number',
        'issued_at',
        'valid_until',
        'status',
        'issued_by',
        'referred_by_customer_profile_id',
        'cancelled_at',
        'cancelled_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'valid_until' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /** Active on a Manila business date: not cancelled, already issued, and not past its valid-until date. */
    public function scopeActiveOn(Builder $query, ?CarbonInterface $moment = null): Builder
    {
        $date = self::businessDate($moment);

        return $query->where('status', self::STATUS_ACTIVE)
            ->whereDate('issued_at', '<=', $date)
            ->whereDate('valid_until', '>=', $date);
    }

    public function isActiveOn(?CarbonInterface $moment = null): bool
    {
        $date = self::businessDate($moment);

        return $this->status === self::STATUS_ACTIVE
            && $this->issued_at->toDateString() <= $date
            && $this->valid_until->toDateString() >= $date;
    }

    public function lifecycle(): string
    {
        return match (true) {
            $this->status === self::STATUS_CANCELLED => 'cancelled',
            $this->valid_until->toDateString() < self::businessDate() => 'expired',
            default => 'active',
        };
    }

    public static function businessDate(?CarbonInterface $moment = null): string
    {
        return ($moment ?? now())->copy()->timezone('Asia/Manila')->toDateString();
    }

    public static function nextCardNumber(): string
    {
        $prefix = (string) config('casa.memberships.card_prefix', 'CPBWS-');
        $highest = static::query()->where('card_number', 'like', $prefix.'%')->pluck('card_number')
            ->map(fn (string $number) => (int) substr($number, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class)->withTrashed();
    }

    public function tier()
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id');
    }

    public function referrer()
    {
        return $this->belongsTo(CustomerProfile::class, 'referred_by_customer_profile_id')->withTrashed();
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function referralCredits()
    {
        return $this->hasMany(MembershipReferralCredit::class);
    }

    public function perkUsages()
    {
        return $this->hasMany(MembershipPerkUsage::class);
    }
}
