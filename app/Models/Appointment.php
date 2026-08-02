<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    /**
     * Statuses that may be selected when creating an appointment internally.
     * Terminal outcomes require an existing confirmed schedule.
     */
    public const CREATION_STATUSES = [
        self::STATUS_CONFIRMED,
    ];

    /**
     * Explicit lifecycle matrix. Repeating the current status permits ordinary
     * record edits without reopening a terminal appointment.
     *
     * @var array<string, array<int, string>>
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_CONFIRMED => [
            self::STATUS_CONFIRMED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
        ],
        self::STATUS_COMPLETED => [self::STATUS_COMPLETED],
        self::STATUS_CANCELLED => [self::STATUS_CANCELLED],
        self::STATUS_NO_SHOW => [self::STATUS_NO_SHOW],
    ];

    protected $fillable = [
        'appointment_number',
        'customer_profile_id',
        'service_id',
        'staff_profile_id',
        'preferred_staff_profile_id',
        'promotion_suggestion_id',
        'requested_start_at',
        'scheduled_start_at',
        'scheduled_end_at',
        'status',
        'customer_notes',
        'internal_notes',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_start_at' => 'datetime',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class)->withTrashed();
    }

    public function service()
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function staffProfile()
    {
        return $this->belongsTo(StaffProfile::class)->withTrashed();
    }

    public function preferredStaffProfile()
    {
        return $this->belongsTo(StaffProfile::class, 'preferred_staff_profile_id')->withTrashed();
    }

    public function promotionSuggestion()
    {
        return $this->belongsTo(PromotionSuggestion::class);
    }

    public function addons()
    {
        return $this->hasMany(AppointmentAddon::class);
    }

    public function paidAddonTotal(): float
    {
        return (float) $this->addons->sum(fn (AppointmentAddon $addon) => (float) $addon->price);
    }

    public function expectedAmount(): float
    {
        return (float) ($this->service?->price ?? 0) + $this->paidAddonTotal();
    }

    /**
     * @return array<int, string>
     */
    public function allowedTargetStatuses(): array
    {
        if (! $this->exists) {
            return self::CREATION_STATUSES;
        }

        return self::STATUS_TRANSITIONS[$this->status] ?? [];
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function latestTransaction()
    {
        return $this->hasOne(Transaction::class)->latestOfMany();
    }

    public function feedback()
    {
        return $this->hasOne(Feedback::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(AppointmentStatusLog::class);
    }

    public function therapistCommissions()
    {
        return $this->hasMany(TherapistCommission::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
