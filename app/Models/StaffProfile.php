<?php

namespace App\Models;

use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_THERAPIST = 'therapist';

    public const TYPES = [self::TYPE_THERAPIST];

    protected $attributes = [
        'staff_type' => self::TYPE_THERAPIST,
    ];

    protected $fillable = [
        'user_id',
        'staff_type',
        'position',
        'specialization',
        'bio',
        'hire_date',
        'is_bookable',
    ];

    protected function casts(): array
    {
        return [
            'staff_type' => 'string',
            'hire_date' => 'date',
            'is_bookable' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function staffServices()
    {
        return $this->hasMany(StaffService::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'staff_services')->withTimestamps();
    }

    public function weeklySchedules()
    {
        return $this->hasMany(StaffWeeklySchedule::class);
    }

    public function scheduleExceptions()
    {
        return $this->hasMany(StaffScheduleException::class);
    }

    public function scheduleShifts()
    {
        return $this->hasMany(StaffScheduleShift::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function preferredAppointments()
    {
        return $this->hasMany(Appointment::class, 'preferred_staff_profile_id');
    }

    public function commissions()
    {
        return $this->hasMany(TherapistCommission::class);
    }

    public function attendances()
    {
        return $this->hasMany(StaffAttendance::class);
    }
}
