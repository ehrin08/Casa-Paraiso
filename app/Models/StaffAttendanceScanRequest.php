<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendanceScanRequest extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = ['staff_profile_id', 'attendance_date', 'qr_bucket', 'scanned_at', 'expires_at', 'status', 'resolution'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'scanned_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function staffProfile() { return $this->belongsTo(StaffProfile::class); }
}
