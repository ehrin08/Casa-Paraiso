<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AppointmentCalendarController extends Controller
{
    public function __invoke(Request $request, AppointmentCalendar $calendar): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
        ]);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $start = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY)->addDay()->startOfDay();
        $customerProfileId = $request->user()->customerProfile?->id;

        abort_unless($customerProfileId, 403);

        return response()->json($calendar->customer($customerProfileId, $start, $end, $data['status'] ?? null));
    }
}
