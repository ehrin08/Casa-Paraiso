<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionIndexRequest;
use App\Http\Requests\Admin\PromotionSettingsRequest;
use App\Models\Addon;
use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Services\RfmRuleEngine;
use App\Services\RfmScorer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(PromotionIndexRequest $request): View
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['q'] ?? ''));
        $lifecycle = (string) ($filters['lifecycle'] ?? '');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $query = PromotionSuggestion::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('suggested_offer', 'like', "%{$search}%")
                        ->orWhereHas('customerProfile', fn (Builder $customers) => $customers->search($search))
                        ->orWhereHas('rfmSegment', fn (Builder $segments) => $segments->where('name', 'like', "%{$search}%"));
                });
            });

        if ($dateFrom !== '') {
            $query->where('created_at', '>=', CarbonImmutable::parse($dateFrom, 'Asia/Manila')->startOfDay()->timezone(config('app.timezone')));
        }

        if ($dateTo !== '') {
            $query->where('created_at', '<', CarbonImmutable::parse($dateTo, 'Asia/Manila')->addDay()->startOfDay()->timezone(config('app.timezone')));
        }

        $query = $this->activityQuery($lifecycle, $query);
        $suggestions = (clone $query)
            ->with(['customerProfile.user', 'appointments:id,promotion_suggestion_id,status'])
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.promotions.index', [
            'segments' => RfmSegment::query()->ordered()->get(),
            'settings' => ApplicationSetting::current(),
            'suggestions' => $suggestions,
            'addonNames' => Addon::query()->whereIn('code', $suggestions->getCollection()->pluck('addon_code')->filter()->unique())->pluck('name', 'code'),
            'search' => $search,
            'lifecycle' => $lifecycle,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'summary' => [
                'available' => $this->activityQuery('available', clone $query)->count(),
                'reserved' => $this->activityQuery('reserved', clone $query)->count(),
                'used' => $this->activityQuery('used', clone $query)->count(),
                'expired' => $this->activityQuery('expired', clone $query)->count(),
            ],
        ]);
    }

    public function editSettings(RfmScorer $scorer, RfmRuleEngine $rules): View
    {
        $segments = RfmSegment::query()->with('recommendedMembershipTier')->ordered()->get();
        $population = $scorer->population();
        $scores = $scorer->scoreAll($population);
        $activeRules = $segments->where('is_active', true)->values();
        $matches = $scores->map(fn (array $customerScores) => $rules->match($customerScores, $activeRules)?->id)->countBy();

        return view('admin.promotions.settings', [
            'segments' => $segments,
            'matches' => $matches,
            'population' => $population->count(),
            'cutoffs' => $scorer->cutoffs($population),
            'settings' => ApplicationSetting::current(),
            'addonNames' => Addon::query()->pluck('name', 'code'),
        ]);
    }

    public function updateSettings(PromotionSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request): void {
            $current = ApplicationSetting::current();

            // Only the reward field changes; defaults fill a missing singleton row.
            ApplicationSetting::updateCurrent(
                [
                    ...($current->exists ? [] : ApplicationSetting::defaults()),
                    'promotion_voucher_validity_days' => $data['promotion_voucher_validity_days'],
                    'updated_by' => $request->user()->id,
                ],
            );
        });

        return to_route('admin.promotions.settings.edit')->with('status', 'customer-rewards-updated');
    }

    public function show(PromotionSuggestion $promotion): View
    {
        $promotion->load(['customerProfile.user', 'rfmSegment', 'appointments.service']);

        return view('admin.promotions.show', [
            'suggestion' => $promotion,
            'rewardName' => $promotion->addonName() ?: $promotion->suggested_offer,
            'canDismiss' => $promotion->isAvailableVoucher(),
        ]);
    }

    public function dismiss(PromotionSuggestion $promotion): RedirectResponse
    {
        DB::transaction(function () use ($promotion): void {
            $promotion = PromotionSuggestion::query()->lockForUpdate()->findOrFail($promotion->id);

            if (! $promotion->isAvailableVoucher()) {
                throw ValidationException::withMessages([
                    'promotion' => __('Only an available customer reward can be dismissed.'),
                ]);
            }

            $promotion->forceFill([
                'status' => PromotionSuggestion::STATUS_DISMISSED,
                'dismissed_at' => now(),
            ])->save();
        }, 3);

        return back()->with('status', 'customer-reward-dismissed');
    }

    private function activityQuery(string $lifecycle, ?Builder $query = null): Builder
    {
        $query ??= PromotionSuggestion::query();

        return match ($lifecycle) {
            'available' => $query->where('status', PromotionSuggestion::STATUS_SUGGESTED)
                ->where(fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            'expired' => $query->where('status', PromotionSuggestion::STATUS_SUGGESTED)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()),
            'reserved' => $query->where('status', PromotionSuggestion::STATUS_APPLIED)
                ->whereHas('appointments', fn (Builder $appointments) => $appointments->where('status', Appointment::STATUS_CONFIRMED)),
            'used' => $query->where('status', PromotionSuggestion::STATUS_APPLIED)
                ->whereDoesntHave('appointments', fn (Builder $appointments) => $appointments->where('status', Appointment::STATUS_CONFIRMED)),
            'dismissed' => $query->where('status', PromotionSuggestion::STATUS_DISMISSED),
            default => $query,
        };
    }
}
