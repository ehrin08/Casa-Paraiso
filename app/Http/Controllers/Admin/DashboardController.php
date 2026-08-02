<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();
        $appointmentSummary = Appointment::query()
            ->selectRaw('SUM(CASE WHEN scheduled_start_at >= ? AND scheduled_start_at < ? THEN 1 ELSE 0 END) AS today_appointments', [$today, $today->copy()->addDay()])
            ->selectRaw('SUM(CASE WHEN status = ? AND scheduled_start_at >= ? THEN 1 ELSE 0 END) AS upcoming_appointments', [Appointment::STATUS_CONFIRMED, now()])
            ->first();

        $summary = [
            'todayAppointments' => (int) $appointmentSummary?->today_appointments,
            'upcomingAppointments' => (int) $appointmentSummary?->upcoming_appointments,
            'todayRevenue' => Transaction::query()
                ->where('payment_status', Transaction::PAYMENT_PAID)
                ->whereDate('paid_at', $today)
                ->sum('amount'),
            'newFeedback' => Feedback::query()
                ->whereDate('submitted_at', $today)
                ->count(),
            'availableRewards' => PromotionSuggestion::query()
                ->where('status', PromotionSuggestion::STATUS_SUGGESTED)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
        ];

        $upcomingAppointments = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '>=', now())
            ->orderBy('scheduled_start_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'summary' => $summary,
            'upcomingAppointments' => $upcomingAppointments,
        ]);
    }
}
