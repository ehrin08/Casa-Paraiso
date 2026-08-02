<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffScheduleWeek extends Model
{
    protected $fillable = ['week_start_date', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['week_start_date' => 'date', 'published_at' => 'datetime'];
    }

    public function shifts()
    {
        return $this->hasMany(StaffScheduleShift::class, 'staff_schedule_week_id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
