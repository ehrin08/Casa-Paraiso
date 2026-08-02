<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\Addon;
use App\Models\PromotionSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RfmAddonVoucher
{
    /**
     * @return Collection<int, PromotionSuggestion>
     */
    public function availableFor(CustomerProfile $customer): Collection
    {
        return PromotionSuggestion::query()
            ->with(['promotionRule.rfmSegment'])
            ->where('customer_profile_id', $customer->id)
            ->where('status', PromotionSuggestion::STATUS_SUGGESTED)
            ->whereNotNull('addon_code')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->oldest()
            ->get()
            ->filter(fn (PromotionSuggestion $voucher) => $voucher->addonName() !== null && Addon::query()->where('code', $voucher->addon_code)->where('is_active', true)->exists())
            ->values();
    }

    public function reserve(int $suggestionId, int $customerProfileId): PromotionSuggestion
    {
        $voucher = PromotionSuggestion::query()
            ->with(['promotionRule.rfmSegment'])
            ->lockForUpdate()
            ->findOrFail($suggestionId);

        $available = (int) $voucher->customer_profile_id === $customerProfileId
            && $voucher->status === PromotionSuggestion::STATUS_SUGGESTED
            && $voucher->addon_code
            && $voucher->addonName()
            && Addon::query()->where('code', $voucher->addon_code)->where('is_active', true)->exists()
            && ! $voucher->isExpired();

        if (! $available) {
            throw ValidationException::withMessages([
                'promotion_suggestion_id' => __('This add-on voucher is no longer available. Choose another voucher or continue without one.'),
            ]);
        }

        $voucher->forceFill([
            'status' => PromotionSuggestion::STATUS_APPLIED,
            'applied_at' => now(),
            'dismissed_at' => null,
        ])->save();

        return $voucher;
    }

    public function release(PromotionSuggestion $voucher): void
    {
        $locked = PromotionSuggestion::query()->lockForUpdate()->find($voucher->id);

        if ($locked?->status !== PromotionSuggestion::STATUS_APPLIED) {
            return;
        }

        $locked->forceFill([
            'status' => PromotionSuggestion::STATUS_SUGGESTED,
            'applied_at' => null,
        ])->save();
    }
}
