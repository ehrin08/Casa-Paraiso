<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StaffScheduleConflictException;
use App\Http\Controllers\Controller;
use App\Models\StaffScheduleShift;
use App\Models\StaffScheduleWeek;
use App\Services\WeeklyRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyRosterController extends Controller
{
    public function show(Request $request, WeeklyRoster $roster): JsonResponse
    {
        $data = $request->validate(['week' => ['required', 'date_format:Y-m-d']]);

        return response()->json($roster->payload($data['week']));
    }

    public function copy(Request $request, WeeklyRoster $roster): JsonResponse
    {
        $data = $request->validate(['week' => ['required', 'date_format:Y-m-d']]);
        $roster->copyPrevious($data['week']);

        return response()->json($roster->payload($data['week']));
    }

    public function storeShift(Request $request, StaffScheduleWeek $scheduleWeek, WeeklyRoster $roster): JsonResponse
    {
        $data = $request->validate(['staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'], 'schedule_date' => ['required', 'date_format:Y-m-d'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i'], 'ends_next_day' => ['nullable', 'boolean']]);
        $roster->saveShift($scheduleWeek, $data);

        return response()->json($roster->payload($scheduleWeek->week_start_date));
    }

    public function destroyShift(StaffScheduleWeek $scheduleWeek, StaffScheduleShift $shift, WeeklyRoster $roster): JsonResponse
    {
        $roster->deleteShift($scheduleWeek, $shift);

        return response()->json($roster->payload($scheduleWeek->week_start_date));
    }

    public function publish(StaffScheduleWeek $scheduleWeek, WeeklyRoster $roster): JsonResponse
    {
        try {
            $roster->publish($scheduleWeek, request()->user()->id);
        } catch (StaffScheduleConflictException $exception) {
            return response()->json(['message' => __('Publishing would leave confirmed appointments outside therapist hours.'), 'conflicts' => $exception->conflicts], 422);
        }

        return response()->json($roster->payload($scheduleWeek->week_start_date));
    }
}
