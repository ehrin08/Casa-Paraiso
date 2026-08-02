<?php

namespace App\Models;

use Database\Factories\TherapistCommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistCommission extends Model
{
    /** @use HasFactory<TherapistCommissionFactory> */
    use HasFactory;

    public const TYPE_EARNING = 'earning';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [self::TYPE_EARNING, self::TYPE_ADJUSTMENT];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_PAID];

    protected $fillable = [
        'staff_profile_id',
        'appointment_id',
        'transaction_id',
        'primary_transaction_id',
        'adjusts_commission_id',
        'commission_type',
        'status',
        'basis_amount',
        'commission_rate',
        'commission_amount',
        'earned_at',
        'paid_at',
        'paid_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'basis_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'earned_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function staffProfile()
    {
        return $this->belongsTo(StaffProfile::class)->withTrashed();
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function adjustedCommission()
    {
        return $this->belongsTo(self::class, 'adjusts_commission_id');
    }

    public function adjustments()
    {
        return $this->hasMany(self::class, 'adjusts_commission_id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
