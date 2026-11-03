<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_VOID = 'void';

    public const PAYMENT_STATUSES = [
        self::PAYMENT_UNPAID,
        self::PAYMENT_PARTIAL,
        self::PAYMENT_PAID,
        self::PAYMENT_REFUNDED,
        self::PAYMENT_VOID,
    ];

    public const PAYMENT_RECEIVED_STATUSES = [
        self::PAYMENT_PARTIAL,
        self::PAYMENT_PAID,
        self::PAYMENT_REFUNDED,
    ];

    public const METHOD_CASH = 'Cash';

    public const METHOD_GCASH = 'GCash';

    public const METHOD_BANK_TRANSFER = 'Bank transfer';

    public const METHOD_OTHER = 'Other';

    public const PAYMENT_METHODS = [
        self::METHOD_CASH,
        self::METHOD_GCASH,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_OTHER,
    ];

    protected $fillable = [
        'transaction_number',
        'customer_profile_id',
        'appointment_id',
        'service_id',
        'amount',
        'gross_amount',
        'discount_amount',
        'customer_membership_id',
        'gift_voucher_id',
        'gift_voucher_applied',
        'amount_due',
        'payment_status',
        'payment_method',
        'paid_at',
        'recorded_by',
        'notes',
        'import_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'gift_voucher_applied' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /** Leaves out the pooled walk-in profile that imported service history uses. */
    public function scopeExcludingImportWalkIn(Builder $query): Builder
    {
        return $query->whereNotIn($query->qualifyColumn('customer_profile_id'), CustomerProfile::withTrashed()
            ->select('id')
            ->where('customer_code', config('casa.imports.walk_in_customer_code')));
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class)->withTrashed();
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function therapistCommissions()
    {
        return $this->hasMany(TherapistCommission::class);
    }

    public function adjustments()
    {
        return $this->hasMany(TransactionAdjustment::class);
    }

    public function customerMembership()
    {
        return $this->belongsTo(CustomerMembership::class);
    }

    public function giftVoucher()
    {
        return $this->belongsTo(GiftVoucher::class);
    }
}
