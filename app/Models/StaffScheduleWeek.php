<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffScheduleWeek extends Model
{
    protected $attributes = ['revision' => 0];

    protected $fillable = ['week_start_date', 'published_at', 'published_by', 'draft_initialized_at', 'revision'];

    protected function casts(): array
    {
        return ['week_start_date' => 'date', 'published_at' => 'datetime', 'draft_initialized_at' => 'datetime', 'revision' => 'integer'];
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
