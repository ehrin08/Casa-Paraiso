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
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
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
            'presets' => $this->presets(),
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

    public function editSettings(): View
    {
        return view('admin.promotions.settings', [
            'presets' => $this->presets(),
            'settings' => ApplicationSetting::current(),
            'addons' => Addon::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateSettings(PromotionSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request): void {
            $current = ApplicationSetting::current();

            ApplicationSetting::updateCurrent(
                [
                    ...ApplicationSetting::defaults(),
                    ...$current->only([
                        'business_name', 'contact_email', 'contact_phone', 'business_address',
                        'location_landmarks', 'facebook_url', 'messenger_url', 'map_url',
                        'company_history', 'organization_chart', 'default_payment_method',
                    ]),
                    'promotion_voucher_validity_days' => $data['promotion_voucher_validity_days'],
                    'updated_by' => $request->user()->id,
                ],
            );

            foreach ($data['groups'] as $key => $group) {
                RfmSegment::query()
                    ->where('preset_key', $key)
                    ->update([
                        'addon_code' => $group['addon_code'],
                        'is_active' => (bool) $group['is_active'],
                    ]);
            }
        });

        return to_route('admin.promotions.index')->with('status', 'customer-rewards-updated');
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

    private function presets(): Collection
    {
        $segments = RfmSegment::query()->whereNotNull('preset_key')->get()->keyBy('preset_key');

        return collect(config('casa.customer_rewards.presets', []))->map(fn (array $preset) => [
            ...$preset,
            'segment' => $segments->get($preset['key']),
        ]);
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
