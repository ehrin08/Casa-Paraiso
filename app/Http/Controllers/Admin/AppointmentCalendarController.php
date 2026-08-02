<?php

namespace App\Http\Controllers\Admin;

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
            'mode' => ['nullable', Rule::in(['bookings', 'availability'])],
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
            'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
        ]);

        $start = Carbon::createFromFormat('Y-m-d', $data['start'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['end'])->startOfDay();

        if ($end->lte($start) || $start->diffInDays($end) > 8) {
            throw ValidationException::withMessages(['end' => __('Calendar ranges must cover no more than eight days.')]);
        }

        return response()->json($calendar->admin($start, $end, $data['mode'] ?? 'bookings', $data));
    }
}
