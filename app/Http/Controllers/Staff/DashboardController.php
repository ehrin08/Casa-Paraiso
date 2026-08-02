<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\TherapistCommission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = today();
        $staffProfile = $request->user()->staffProfile;
        $summary = [
            'assignedToday' => 0,
            'upcoming' => 0,
            'completedToday' => 0,
        ];
        $commissionTotals = [
            'pending' => 0,
            'paid' => 0,
            'net' => 0,
        ];

        $todayAppointments = collect();

        if ($staffProfile) {
            $appointmentSummary = Appointment::query()
                ->where('staff_profile_id', $staffProfile->id)
                ->selectRaw('SUM(CASE WHEN status = ? AND scheduled_start_at >= ? AND scheduled_start_at < ? THEN 1 ELSE 0 END) AS assigned_today', [Appointment::STATUS_CONFIRMED, $today, $today->copy()->addDay()])
                ->selectRaw('SUM(CASE WHEN status = ? AND scheduled_start_at > ? THEN 1 ELSE 0 END) AS upcoming', [Appointment::STATUS_CONFIRMED, now()])
                ->selectRaw('SUM(CASE WHEN status = ? AND completed_at >= ? AND completed_at < ? THEN 1 ELSE 0 END) AS completed_today', [Appointment::STATUS_COMPLETED, $today, $today->copy()->addDay()])
                ->first();
            $summary = ['assignedToday' => (int) $appointmentSummary?->assigned_today, 'upcoming' => (int) $appointmentSummary?->upcoming, 'completedToday' => (int) $appointmentSummary?->completed_today];

            $todayAppointments = Appointment::query()
                ->with(['customerProfile.user', 'service'])
                ->where('staff_profile_id', $staffProfile->id)
                ->whereDate('scheduled_start_at', $today)
                ->orderBy('scheduled_start_at')
                ->limit(6)
                ->get();

            $commissionSummary = TherapistCommission::query()
                ->where('staff_profile_id', $staffProfile->id)
                ->selectRaw('SUM(CASE WHEN status = ? THEN commission_amount ELSE 0 END) AS pending', [TherapistCommission::STATUS_PENDING])
                ->selectRaw('SUM(CASE WHEN status = ? THEN commission_amount ELSE 0 END) AS paid', [TherapistCommission::STATUS_PAID])
                ->selectRaw('SUM(commission_amount) AS net')
                ->first();
            $commissionTotals = ['pending' => $commissionSummary?->pending ?? 0, 'paid' => $commissionSummary?->paid ?? 0, 'net' => $commissionSummary?->net ?? 0];
        }

        return view('staff.dashboard', [
            'summary' => $summary,
            'commissionTotals' => $commissionTotals,
            'staffProfile' => $staffProfile,
            'todayAppointments' => $todayAppointments,
        ]);
    }
}
