<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GiftVoucher extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'code',
        'value',
        'status',
        'issued_at',
        'expires_at',
        'purchaser_name',
        'purchaser_customer_profile_id',
        'sale_payment_method',
        'sold_by',
        'redeemed_transaction_id',
        'redeemed_at',
        'applied_amount',
        'forfeited_amount',
        'voided_at',
        'voided_by',
        'void_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'issued_at' => 'datetime',
            'expires_at' => 'date',
            'redeemed_at' => 'datetime',
            'applied_amount' => 'decimal:2',
            'forfeited_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public static function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    public static function generateCode(): string
    {
        do {
            $code = 'CPGV-'.Str::upper(Str::random(6));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /** Active, unredeemed vouchers still inside their Manila expiry date. */
    public function scopeRedeemable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhereDate('expires_at', '>=', CustomerMembership::businessDate()));
    }

    public function isRedeemable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->expires_at === null || $this->expires_at->toDateString() >= CustomerMembership::businessDate());
    }

    public function lifecycle(): string
    {
        return match (true) {
            $this->status === self::STATUS_VOID => 'void',
            $this->status === self::STATUS_REDEEMED => 'redeemed',
            ! $this->isRedeemable() => 'expired',
            default => 'active',
        };
    }

    public function purchaser()
    {
        return $this->belongsTo(CustomerProfile::class, 'purchaser_customer_profile_id')->withTrashed();
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function redeemedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'redeemed_transaction_id');
    }
}
