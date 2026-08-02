<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentCalendarController extends Controller
{
    public function __invoke(Request $request, AppointmentCalendar $calendar): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
        ]);
        $start = Carbon::createFromFormat('Y-m-d', $data['start'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['end'])->startOfDay();

        if ($end->lte($start) || $start->diffInDays($end) > 8) {
            throw ValidationException::withMessages(['end' => __('Calendar ranges must cover no more than eight days.')]);
        }

        abort_unless($request->user()->staffProfile, 403);

        return response()->json($calendar->staff($request->user()->staffProfile, $start, $end, $data['status'] ?? null));
    }
}
