<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $now = CarbonImmutable::now('Asia/Manila');
        $today = $now->startOfDay();
        $weekStart = $today->startOfWeek();
        $appointmentSummary = Appointment::query()
            ->selectRaw('SUM(CASE WHEN scheduled_start_at >= ? AND scheduled_start_at < ? THEN 1 ELSE 0 END) AS today_appointments', [$today, $today->copy()->addDay()])
            ->selectRaw('SUM(CASE WHEN status = ? AND scheduled_start_at >= ? THEN 1 ELSE 0 END) AS upcoming_appointments', [Appointment::STATUS_CONFIRMED, $now])
            ->selectRaw('SUM(CASE WHEN status = ? AND scheduled_start_at >= ? AND scheduled_start_at < ? THEN 1 ELSE 0 END) AS confirmed_today', [Appointment::STATUS_CONFIRMED, $today, $today->copy()->addDay()])
            ->selectRaw('SUM(CASE WHEN status = ? AND scheduled_start_at < ? THEN 1 ELSE 0 END) AS overdue_confirmed', [Appointment::STATUS_CONFIRMED, $now])
            ->first();

        $summary = [
            'todayAppointments' => (int) $appointmentSummary?->today_appointments,
            'upcomingAppointments' => (int) $appointmentSummary?->upcoming_appointments,
            'confirmedToday' => (int) $appointmentSummary?->confirmed_today,
            'overdueConfirmed' => (int) $appointmentSummary?->overdue_confirmed,
            'todayRevenue' => Transaction::query()
                ->where('payment_status', Transaction::PAYMENT_PAID)
                ->where('paid_at', '>=', $today)
                ->where('paid_at', '<', $today->addDay())
                ->sum('amount'),
            'newFeedback' => Feedback::query()
                ->where('submitted_at', '>=', $today)
                ->where('submitted_at', '<', $today->addDay())
                ->count(),
            'availableRewards' => PromotionSuggestion::query()
                ->where('status', PromotionSuggestion::STATUS_SUGGESTED)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                ->count(),
        ];

        $upcomingAppointments = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '>=', $now)
            ->orderBy('scheduled_start_at')
            ->orderBy('id')
            ->limit(5)
            ->get();

        $latestFeedback = Feedback::query()
            ->with('customerProfile.user')
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        $visitsByDate = Appointment::query()
            ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED])
            ->where('scheduled_start_at', '>=', $weekStart)
            ->where('scheduled_start_at', '<', $weekStart->addWeek())
            ->selectRaw('DATE(scheduled_start_at) AS visit_date, COUNT(*) AS visits')
            ->groupByRaw('DATE(scheduled_start_at)')
            ->pluck('visits', 'visit_date');

        $weeklyVisits = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $visitsByDate): array {
            $date = $weekStart->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'day' => $date->format('D'),
                'label' => $date->format('l, M j'),
                'count' => (int) ($visitsByDate[$date->toDateString()] ?? 0),
            ];
        });

        return view('admin.dashboard', [
            'dashboardDate' => $today,
            'summary' => $summary,
            'upcomingAppointments' => $upcomingAppointments,
            'latestFeedback' => $latestFeedback,
            'weeklyVisits' => $weeklyVisits,
            'weeklyMaximum' => max(1, $weeklyVisits->max('count')),
        ]);
    }
}
