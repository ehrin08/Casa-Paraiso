<?php

namespace App\Models;

use Database\Factories\StaffWeeklyScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffWeeklySchedule extends Model
{
    /** @use HasFactory<StaffWeeklyScheduleFactory> */
    use HasFactory;

    public const SUNDAY = 0;

    public const MONDAY = 1;

    public const TUESDAY = 2;

    public const WEDNESDAY = 3;

    public const THURSDAY = 4;

    public const FRIDAY = 5;

    public const SATURDAY = 6;

    public const DAYS = [
        self::SUNDAY => 'Sunday',
        self::MONDAY => 'Monday',
        self::TUESDAY => 'Tuesday',
        self::WEDNESDAY => 'Wednesday',
        self::THURSDAY => 'Thursday',
        self::FRIDAY => 'Friday',
        self::SATURDAY => 'Saturday',
    ];

    protected $fillable = [
        'staff_profile_id',
        'day_of_week',
        'start_time',
        'end_time',
        'ends_next_day',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'ends_next_day' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function staffProfile()
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function dayName(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }
}
