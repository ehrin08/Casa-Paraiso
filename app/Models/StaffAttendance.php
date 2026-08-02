<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    protected $fillable = ['staff_profile_id', 'attendance_date', 'time_in_at', 'time_out_at'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'time_in_at' => 'datetime', 'time_out_at' => 'datetime'];
    }

    public function staffProfile() { return $this->belongsTo(StaffProfile::class); }
    public function events() { return $this->hasMany(StaffAttendanceEvent::class); }
}
