<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AttendanceCorrectionRequest;
use App\Http\Requests\Admin\AttendanceIndexRequest;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Services\AttendanceQr;
use App\Services\AttendanceWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebAttendanceController extends Controller
{
    public function staff(Request $request): View
    {
        $staff = $request->user()->staffProfile;
        abort_unless($staff, 403);
        $attendance = StaffAttendance::query()->with('events.recorder')->where('staff_profile_id', $staff->id)->where('attendance_date', CarbonImmutable::now('Asia/Manila')->toDateString())->first();

        return view('staff.attendance', compact('attendance'));
    }

    public function submitScan(Request $request, AttendanceWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['payload' => ['required', 'string', 'max:4096']]);
        $staff = $request->user()->staffProfile;
        abort_unless($staff, 403);
        $attendance = $workflow->scanAndVerify($staff, $request->user(), $data['payload']);
        $action = $attendance->time_out_at ? 'time out' : 'time in';

        return response()->json(['message' => "Attendance {$action} recorded automatically."], 201);
    }

    public function station(AttendanceQr $qr): View
    {
        return view('attendance.station', ['qr' => $qr->current()]);
    }

    public function qr(AttendanceQr $qr): JsonResponse
    {
        return response()->json(['qr' => $qr->current()]);
    }

    public function index(AttendanceIndexRequest $request): View
    {
        $filters = AttendanceIndexRequest::normalizeFilters($request->validated());
        $records = $this->attendanceQuery($filters)
            ->with(['staffProfile' => fn ($query) => $query->withTrashed()->with('user')])
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->appends($filters);
        $selectedId = filter_var($request->query('selected'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $selectedRecord = $selectedId === 0 ? null : (
            $records->getCollection()->firstWhere('id', $selectedId ?: null) ?? $records->getCollection()->first()
        );
        $selectedRecord?->load(['events' => fn ($query) => $query->with('recorder')->orderByDesc('id')]);

        return view('admin.attendance.index', [
            'records' => $records,
            'staffProfiles' => StaffProfile::withTrashed()->with('user')->where('staff_type', StaffProfile::TYPE_THERAPIST)->get()->sortBy('user.name')->values(),
            'filters' => $filters,
            'selectedRecord' => $selectedRecord,
            'correctionReasons' => AttendanceCorrectionRequest::REASONS,
        ]);
    }

    public function correct(AttendanceCorrectionRequest $request, StaffAttendance $attendance, AttendanceWorkflow $workflow): RedirectResponse
    {
        $data = $request->validated();
        try {
            $workflow->correct($attendance, $data['action'], CarbonImmutable::parse($data['occurred_at'], 'Asia/Manila'), $request->reasonText(), $request->user());
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($request->attendanceUrl());
        }

        $query = $request->returnQuery();
        $lastPage = max(1, (int) ceil($this->attendanceQuery($query)->count() / (int) config('casa.pagination.per_page', 15)));
        $query['page'] = min($query['page'], $lastPage);

        return redirect()->route('admin.attendance.index', $query)->with('status', 'attendance-corrected');
    }

    private function attendanceQuery(array $filters): Builder
    {
        return StaffAttendance::query()
            ->when($filters['staff_profile_id'] ?? null, fn ($query, $id) => $query->where('staff_profile_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $status === 'open'
                ? $query->whereNotNull('time_in_at')->whereNull('time_out_at')
                : $query->whereNotNull('time_out_at'))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', $date))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '<=', $date))
            ->orderByDesc('attendance_date')->orderByDesc('id');
    }
}
