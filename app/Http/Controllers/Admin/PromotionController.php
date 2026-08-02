<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionSettingsRequest;
use App\Models\ApplicationSetting;
use App\Models\Addon;
use App\Models\Appointment;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $lifecycle = (string) $request->query('lifecycle');
        $suggestions = $this->activityQuery($lifecycle)
            ->with(['customerProfile.user', 'rfmSegment', 'appointments:id,promotion_suggestion_id,status'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('suggested_offer', 'like', "%{$search}%")
                        ->orWhereHas('customerProfile.user', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('rfmSegment', fn (Builder $segments) => $segments->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        $segments = RfmSegment::query()
            ->whereNotNull('preset_key')
            ->get()
            ->keyBy('preset_key');

        return view('admin.promotions.index', [
            'presets' => collect(config('casa.customer_rewards.presets', []))->map(fn (array $preset) => [
                ...$preset,
                'segment' => $segments->get($preset['key']),
            ]),
            'settings' => ApplicationSetting::current(),
            'suggestions' => $suggestions,
            'search' => $search,
            'lifecycle' => $lifecycle,
            'summary' => [
                'available' => $this->activityQuery('available')->count(),
                'reserved' => $this->activityQuery('reserved')->count(),
                'used' => $this->activityQuery('used')->count(),
                'expired' => $this->activityQuery('expired')->count(),
            ],
            'addons' => Addon::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateSettings(PromotionSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request): void {
            ApplicationSetting::updateCurrent(
                [
                    'business_name' => ApplicationSetting::current()->business_name,
                    'contact_email' => ApplicationSetting::current()->contact_email,
                    'contact_phone' => ApplicationSetting::current()->contact_phone,
                    'business_address' => ApplicationSetting::current()->business_address,
                    'default_payment_method' => ApplicationSetting::current()->default_payment_method,
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

        return back()->with('status', 'customer-rewards-updated');
    }

    public function show(PromotionSuggestion $promotion): View
    {
        $promotion->load(['customerProfile.user', 'rfmSegment', 'appointments.service']);

        return view('admin.promotions.show', ['suggestion' => $promotion]);
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

    private function activityQuery(string $lifecycle): Builder
    {
        $query = PromotionSuggestion::query();

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
