<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffScheduleShift extends Model
{
    public const VERSION_DRAFT = 'draft';

    public const VERSION_PUBLISHED = 'published';

    protected $fillable = ['staff_schedule_week_id', 'staff_profile_id', 'version', 'schedule_date', 'start_time', 'end_time', 'ends_next_day'];

    protected function casts(): array
    {
        return ['schedule_date' => 'date', 'ends_next_day' => 'boolean'];
    }

    public function week()
    {
        return $this->belongsTo(StaffScheduleWeek::class, 'staff_schedule_week_id');
    }

    public function staffProfile()
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
