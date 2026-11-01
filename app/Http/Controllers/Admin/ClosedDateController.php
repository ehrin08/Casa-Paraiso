<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClosedDateRequest;
use App\Models\Appointment;
use App\Models\ClosedDate;
use App\Models\StaffProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClosedDateController extends Controller
{
    public function index(): View
    {
        $today = now(config('casa.business_hours.timezone', 'Asia/Manila'))->toDateString();

        return view('admin.closed-dates.index', [
            'closedDates' => ClosedDate::query()
                ->with('creator')
                ->whereDate('closed_on', '>=', $today)
                ->orderBy('closed_on')
                ->paginate(config('casa.pagination.per_page', 15)),
            'conflicts' => collect(session('closed_date_conflicts', [])),
        ]);
    }

    public function store(ClosedDateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $timezone = (string) config('casa.business_hours.timezone', 'Asia/Manila');
        $dayStart = Carbon::createFromFormat('!Y-m-d', $data['closed_on'], $timezone);

        $conflicts = DB::transaction(function () use ($data, $dayStart, $request) {
            // Bookings lock therapist rows before checking the schedule, so locking them all first
            // means no booking can land on this date between the check below and the insert.
            StaffProfile::query()->orderBy('id')->lockForUpdate()->pluck('id');

            $conflicts = Appointment::query()
                ->with(['customerProfile.user', 'staffProfile.user'])
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->where('scheduled_start_at', '>=', $dayStart)
                ->where('scheduled_start_at', '<', $dayStart->copy()->addDay())
                ->orderBy('scheduled_start_at')
                ->get();

            if ($conflicts->isEmpty()) {
                ClosedDate::query()->create([...$data, 'created_by' => $request->user()->id]);
            }

            return $conflicts;
        });

        if ($conflicts->isNotEmpty()) {
            return back()->withInput()
                ->withErrors(['closed_on' => trans_choice('This date has :count confirmed appointment. Move or cancel it first.|This date has :count confirmed appointments. Move or cancel them first.', $conflicts->count())])
                ->with('closed_date_conflicts', $conflicts->map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'time' => $appointment->scheduled_start_at?->copy()->timezone($timezone)->format('g:i A'),
                    'customer' => $appointment->customerProfile?->display_name ?? __('Customer'),
                    'therapist' => $appointment->staffProfile?->user?->name ?? __('Unassigned'),
                ])->all());
        }

        return to_route('admin.closed-dates.index')->with('status', 'closed-date-created');
    }

    public function destroy(ClosedDate $closedDate): RedirectResponse
    {
        $closedDate->delete();

        return to_route('admin.closed-dates.index')->with('status', 'closed-date-deleted');
    }
}
