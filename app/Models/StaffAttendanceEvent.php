<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendanceEvent extends Model
{
    protected $fillable = ['staff_attendance_id', 'staff_profile_id', 'scan_request_id', 'event_type', 'source', 'occurred_at', 'recorded_by', 'reason'];

    protected function casts(): array { return ['occurred_at' => 'datetime']; }

    public function attendance() { return $this->belongsTo(StaffAttendance::class, 'staff_attendance_id'); }
    public function staffProfile() { return $this->belongsTo(StaffProfile::class); }
    public function scanRequest() { return $this->belongsTo(StaffAttendanceScanRequest::class, 'scan_request_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
