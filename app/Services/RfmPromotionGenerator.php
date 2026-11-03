<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\PromotionSuggestion;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class RfmPromotionGenerator
{
    public function __construct(
        private readonly RfmScorer $scorer,
        private readonly RfmRuleEngine $rules,
    ) {}

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

            if ($customer->is_import_walk_in || $this->hasActiveEntitlement($customer)) {
                return null;
            }

            $population = $this->scorer->population();
            $metrics = $population->get($customer->id) ?? $this->scorer->metrics(null, 0, 0);
            $scores = $this->scorer->score($metrics, $population);
            $segment = $this->rules->match($scores);

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
                'r_score' => $scores['r'],
                'f_score' => $scores['f'],
                'm_score' => $scores['m'],
                'suggested_offer' => __('Complimentary :addon add-on voucher', ['addon' => $segment->addonName()]),
                'addon_code' => $segment->addon_code,
                'status' => PromotionSuggestion::STATUS_SUGGESTED,
                'expires_at' => $validityDays === null ? null : now()->addDays($validityDays),
            ]);
        }, 3);
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
