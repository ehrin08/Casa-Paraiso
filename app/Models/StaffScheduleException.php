<?php

namespace App\Models;

use Database\Factories\StaffScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffScheduleException extends Model
{
    /** @use HasFactory<StaffScheduleExceptionFactory> */
    use HasFactory;

    public const TYPE_AVAILABLE = 'available';

    public const TYPE_UNAVAILABLE = 'unavailable';

    public const TYPES = [
        self::TYPE_AVAILABLE,
        self::TYPE_UNAVAILABLE,
    ];

    protected $fillable = [
        'staff_profile_id',
        'exception_date',
        'exception_type',
        'start_time',
        'end_time',
        'ends_next_day',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'exception_date' => 'date',
            'ends_next_day' => 'boolean',
        ];
    }

    public function staffProfile()
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
