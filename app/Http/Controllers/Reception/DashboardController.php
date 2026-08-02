<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();
        $appointmentSummary = Appointment::query()
            ->selectRaw('SUM(CASE WHEN scheduled_start_at >= ? AND scheduled_start_at < ? THEN 1 ELSE 0 END) AS today_count', [$today, $today->copy()->addDay()])
            ->selectRaw('SUM(CASE WHEN status = ? AND scheduled_start_at >= ? THEN 1 ELSE 0 END) AS upcoming_count', [Appointment::STATUS_CONFIRMED, now()])
            ->first();

        return view('reception.dashboard', [
            'summary' => [
                'today' => (int) $appointmentSummary?->today_count,
                'upcoming' => (int) $appointmentSummary?->upcoming_count,
                'customers' => CustomerProfile::query()->count(),
                'paymentsToday' => Transaction::query()->whereDate('paid_at', today())->sum('amount'),
            ],
            'todayAppointments' => Appointment::query()->with(['customerProfile.user', 'service', 'staffProfile.user'])->whereBetween('scheduled_start_at', [$today, $today->copy()->addDay()])->orderBy('scheduled_start_at')->limit(8)->get(),
        ]);
    }
}
