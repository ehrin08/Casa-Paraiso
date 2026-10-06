<?php

namespace App\Http\Controllers;

use App\Http\Requests\OperationalAppointmentAvailabilityRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\AppointmentAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OperationalAppointmentAvailabilityController extends Controller
{
    public function __invoke(OperationalAppointmentAvailabilityRequest $request, AppointmentAvailability $availability): JsonResponse
    {
        $data = $request->validated();
        $appointment = ! empty($data['appointment_id']) ? Appointment::query()->findOrFail($data['appointment_id']) : null;
        $service = Service::query()->findOrFail($data['service_id']);

        if (! $service->is_active && (int) $appointment?->service_id !== (int) $service->id) {
            throw ValidationException::withMessages(['service_id' => __('Select an active service.')]);
        }

        return response()->json($availability->forOperations($service, $data['month'], $data['addon_codes'] ?? [], $appointment))
            ->header('Cache-Control', 'private, no-store');
    }
}
