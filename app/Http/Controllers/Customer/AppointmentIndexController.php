<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentIndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        $customerProfile = $request->user()->customerProfile;
        $customerProfileId = $customerProfile?->id ?? 0;

        $summary = [
            'upcoming' => Appointment::query()
                ->where('customer_profile_id', $customerProfileId)
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->where('scheduled_start_at', '>=', now())
                ->count(),
            'cancelled' => Appointment::query()
                ->where('customer_profile_id', $customerProfileId)
                ->where('status', Appointment::STATUS_CANCELLED)
                ->count(),
            'completed' => Appointment::query()
                ->where('customer_profile_id', $customerProfileId)
                ->where('status', Appointment::STATUS_COMPLETED)
                ->count(),
        ];

        $appointments = Appointment::query()
            ->with(['service', 'staffProfile.user'])
            ->where('customer_profile_id', $customerProfileId)
            ->latest('requested_start_at')
            ->limit(8)
            ->get();

        return view('customer.appointments.index', [
            'summary' => $summary,
            'appointments' => $appointments,
        ]);
    }
}
