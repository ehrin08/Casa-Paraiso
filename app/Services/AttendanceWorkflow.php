<?php

namespace App\Services;

use App\Models\{StaffAttendance, StaffAttendanceEvent, StaffAttendanceScanRequest, StaffProfile, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AttendanceWorkflow
{
    public function scanAndVerify(StaffProfile $staff, User $actor, string $payload): StaffAttendance
    {
        abort_unless($staff->staff_type === StaffProfile::TYPE_THERAPIST && $staff->user?->is_active, 422, 'Only active therapists can use attendance scanning.');
        $bucket = app(AttendanceQr::class)->validate($payload); $now = CarbonImmutable::now('Asia/Manila');
        return DB::transaction(function () use ($staff, $actor, $bucket, $now): StaffAttendance {
            $existingScan = StaffAttendanceScanRequest::query()
                ->where('staff_profile_id', $staff->id)
                ->where('qr_bucket', $bucket)
                ->lockForUpdate()
                ->first();
            abort_if($existingScan, 422, 'This attendance QR code was already scanned. Wait for the next live code before scanning again.');

            $attendance = StaffAttendance::query()->firstOrCreate([
                'staff_profile_id' => $staff->id,
                'attendance_date' => $now->toDateString(),
            ]);
            $attendance->refresh();
            $action = ! $attendance->time_in_at ? 'time_in' : 'time_out';
            abort_if($attendance->time_out_at, 422, 'Your attendance is already complete for today.');

            $scan = StaffAttendanceScanRequest::create([
                'staff_profile_id' => $staff->id,
                'attendance_date' => $now->toDateString(),
                'qr_bucket' => $bucket,
                'scanned_at' => $now,
                'expires_at' => $now->startOfMinute()->addMinute(),
                'status' => StaffAttendanceScanRequest::STATUS_CONFIRMED,
                'resolution' => $action,
            ]);
            $attendance->update([$action === 'time_in' ? 'time_in_at' : 'time_out_at' => $now]);
            StaffAttendanceEvent::create([
                'staff_attendance_id' => $attendance->id,
                'staff_profile_id' => $attendance->staff_profile_id,
                'scan_request_id' => $scan->id,
                'event_type' => $action,
                'source' => 'auto_verified_qr',
                'occurred_at' => $now,
                'recorded_by' => $actor->id,
            ]);

            return $attendance->fresh(['staffProfile.user', 'events.recorder']);
        });
    }

    public function correct(StaffAttendance $attendance, string $action, CarbonImmutable $occurredAt, string $reason, User $actor): StaffAttendance
    {
        return DB::transaction(function () use ($attendance, $action, $occurredAt, $reason, $actor): StaffAttendance {
            $attendance = StaffAttendance::query()->lockForUpdate()->findOrFail($attendance->id);
            abort_unless($occurredAt->timezone('Asia/Manila')->toDateString() === $attendance->attendance_date->toDateString(), 422, 'Correction time must be within the attendance date.');
            if ($action === 'time_in') { abort_if($attendance->time_out_at && $occurredAt->greaterThan($attendance->time_out_at), 422, 'Time in cannot be after time out.'); $attendance->update(['time_in_at' => $occurredAt]); }
            else { abort_if(! $attendance->time_in_at, 422, 'Time out requires a time in.'); abort_if($occurredAt->lessThan($attendance->time_in_at), 422, 'Time out cannot be before time in.'); $attendance->update(['time_out_at' => $occurredAt]); }
            StaffAttendanceEvent::create(['staff_attendance_id' => $attendance->id, 'staff_profile_id' => $attendance->staff_profile_id, 'event_type' => $action, 'source' => 'admin_correction', 'occurred_at' => $occurredAt, 'recorded_by' => $actor->id, 'reason' => $reason]);
            return $attendance->fresh(['staffProfile.user', 'events.recorder']);
        });
    }
}
