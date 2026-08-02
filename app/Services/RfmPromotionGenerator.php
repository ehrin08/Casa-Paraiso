<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RfmPromotionGenerator
{
    public function generateForTransaction(Transaction $transaction): ?PromotionSuggestion
    {
        return DB::transaction(function () use ($transaction): ?PromotionSuggestion {
            $transaction = Transaction::query()
                ->with('appointment')
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            if ($transaction->payment_status !== Transaction::PAYMENT_PAID
                || $transaction->appointment?->status !== Appointment::STATUS_COMPLETED) {
                return null;
            }

            $customer = CustomerProfile::query()
                ->lockForUpdate()
                ->findOrFail($transaction->customer_profile_id);
            $generationKey = hash('sha256', 'paid-transaction|'.$transaction->id);

            $existing = PromotionSuggestion::query()
                ->where('generation_key', $generationKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($this->hasActiveEntitlement($customer)) {
                return null;
            }

            $metrics = $this->metrics($customer);
            $segment = $this->matchingPreset($metrics);

            if (! $segment || ! $segment->addon_code || ! $segment->addonName()) {
                return null;
            }

            $validityDays = $this->validityDays();

            return PromotionSuggestion::query()->create([
                'customer_profile_id' => $customer->id,
                'rfm_segment_id' => $segment->id,
                'promotion_rule_id' => null,
                'generation_key' => $generationKey,
                'recency_days' => $metrics['recency_days'],
                'frequency_count' => $metrics['frequency_count'],
                'monetary_total' => $metrics['monetary_total'],
                'suggested_offer' => __('Complimentary :addon add-on voucher', ['addon' => $segment->addonName()]),
                'addon_code' => $segment->addon_code,
                'status' => PromotionSuggestion::STATUS_SUGGESTED,
                'expires_at' => $validityDays === null ? null : now()->addDays($validityDays),
            ]);
        }, 3);
    }

    /** @return array{recency_days: int, frequency_count: int, monetary_total: float} */
    private function metrics(CustomerProfile $customer): array
    {
        $paidTransactions = Transaction::query()
            ->where('customer_profile_id', $customer->id)
            ->where('payment_status', Transaction::PAYMENT_PAID)
            ->whereHas('appointment', fn ($query) => $query->where('status', Appointment::STATUS_COMPLETED));
        $latestPaidAt = (clone $paidTransactions)->max('paid_at');

        return [
            'recency_days' => $latestPaidAt ? (int) max(0, Carbon::parse($latestPaidAt)->diffInDays(now())) : PHP_INT_MAX,
            'frequency_count' => (clone $paidTransactions)->count(),
            'monetary_total' => (float) (clone $paidTransactions)->sum('amount'),
        ];
    }

    /** @param array{recency_days: int, frequency_count: int, monetary_total: float} $metrics */
    private function matchingPreset(array $metrics): ?RfmSegment
    {
        $segments = RfmSegment::query()
            ->where('is_active', true)
            ->whereNotNull('preset_key')
            ->get()
            ->keyBy('preset_key');

        foreach (config('casa.customer_rewards.presets', []) as $preset) {
            $segment = $segments->get($preset['key']);

            if ($segment && $this->matches($preset, $metrics)) {
                return $segment;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $preset @param array{recency_days: int, frequency_count: int, monetary_total: float} $metrics */
    private function matches(array $preset, array $metrics): bool
    {
        return ($preset['recency_min_days'] === null || $metrics['recency_days'] >= $preset['recency_min_days'])
            && ($preset['recency_max_days'] === null || $metrics['recency_days'] <= $preset['recency_max_days'])
            && ($preset['frequency_min'] === null || $metrics['frequency_count'] >= $preset['frequency_min'])
            && ($preset['frequency_max'] === null || $metrics['frequency_count'] <= $preset['frequency_max'])
            && ($preset['monetary_min'] === null || $metrics['monetary_total'] >= $preset['monetary_min'])
            && ($preset['monetary_max'] === null || $metrics['monetary_total'] <= $preset['monetary_max']);
    }

    private function hasActiveEntitlement(CustomerProfile $customer): bool
    {
        return PromotionSuggestion::query()
            ->where('customer_profile_id', $customer->id)
            ->where(function ($query): void {
                $query->where(function ($available): void {
                    $available->where('status', PromotionSuggestion::STATUS_SUGGESTED)
                        ->whereNotNull('addon_code')
                        ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                })->orWhere(function ($reserved): void {
                    $reserved->where('status', PromotionSuggestion::STATUS_APPLIED)
                        ->whereHas('appointments', fn ($appointments) => $appointments->where('status', Appointment::STATUS_CONFIRMED));
                });
            })
            ->exists();
    }

    private function validityDays(): ?int
    {
        $value = ApplicationSetting::current()->promotion_voucher_validity_days;

        return $value === null ? null : (int) $value;
    }
}
