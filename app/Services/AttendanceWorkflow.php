<?php

namespace App\Services;

use App\Models\StaffAttendance;
use App\Models\StaffAttendanceEvent;
use App\Models\StaffAttendanceScanRequest;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceWorkflow
{
    public function scanAndVerify(StaffProfile $staff, User $actor, string $payload): StaffAttendance
    {
        abort_unless($staff->staff_type === StaffProfile::TYPE_THERAPIST && $staff->user?->is_active, 422, 'Only active therapists can use attendance scanning.');
        $bucket = app(AttendanceQr::class)->validate($payload);
        $now = CarbonImmutable::now('Asia/Manila');

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
        $occurredAt = $occurredAt->timezone('Asia/Manila');

        return DB::transaction(function () use ($attendance, $action, $occurredAt, $reason, $actor): StaffAttendance {
            $attendance = StaffAttendance::query()->lockForUpdate()->findOrFail($attendance->id);
            $error = match (true) {
                $occurredAt->timezone('Asia/Manila')->toDateString() !== $attendance->attendance_date->toDateString() => 'Correction time must be within the attendance date.',
                $action === 'time_in' && $attendance->time_out_at && $occurredAt->greaterThan($attendance->time_out_at) => 'Time in cannot be after time out.',
                $action === 'time_out' && ! $attendance->time_in_at => 'Time out requires a time in.',
                $action === 'time_out' && $occurredAt->lessThan($attendance->time_in_at) => 'Time out cannot be before time in.',
                default => null,
            };
            if ($error) {
                throw ValidationException::withMessages(['occurred_at' => $error]);
            }
            $attendance->update([$action === 'time_in' ? 'time_in_at' : 'time_out_at' => $occurredAt]);
            StaffAttendanceEvent::create(['staff_attendance_id' => $attendance->id, 'staff_profile_id' => $attendance->staff_profile_id, 'event_type' => $action, 'source' => 'admin_correction', 'occurred_at' => $occurredAt, 'recorded_by' => $actor->id, 'reason' => $reason]);

            return $attendance->fresh(['staffProfile.user', 'events.recorder']);
        });
    }
}
