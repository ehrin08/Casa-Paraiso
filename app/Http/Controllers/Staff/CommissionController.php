<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\TherapistCommission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        $staffProfileId = $request->user()->staffProfile?->id;
        abort_unless($staffProfileId, 403);
        $query = TherapistCommission::query()->where('staff_profile_id', $staffProfileId);

        return view('staff.commissions.index', [
            'commissions' => (clone $query)
                ->with(['appointment.service', 'transaction'])
                ->latest('earned_at')
                ->paginate((int) config('casa.pagination.per_page', 15))
                ->withQueryString(),
            'totals' => [
                'pending' => (clone $query)->where('status', TherapistCommission::STATUS_PENDING)->sum('commission_amount'),
                'paid' => (clone $query)->where('status', TherapistCommission::STATUS_PAID)->sum('commission_amount'),
                'net' => (clone $query)->sum('commission_amount'),
            ],
        ]);
    }

    public function show(Request $request, TherapistCommission $commission): View
    {
        $this->authorizeOwnCommission($request, $commission);
        $commission->load(['appointment.service', 'transaction', 'paidBy']);

        return view('staff.commissions.show', ['commission' => $commission]);
    }

    private function authorizeOwnCommission(Request $request, TherapistCommission $commission): void
    {
        abort_unless((int) $commission->staff_profile_id === (int) ($request->user()->staffProfile?->id ?? 0), 403);
    }
}
