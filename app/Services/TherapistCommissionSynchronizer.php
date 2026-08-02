<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TherapistCommissionSynchronizer
{
    public function synchronize(Transaction $source): ?TherapistCommission
    {
        return DB::transaction(function () use ($source): ?TherapistCommission {
            $transaction = Transaction::query()
                ->with('appointment')
                ->lockForUpdate()
                ->findOrFail($source->id);
            $appointment = $transaction->appointment;
            $commissions = TherapistCommission::query()
                ->where('transaction_id', $transaction->id)
                ->lockForUpdate()
                ->get();
            $primary = $commissions->firstWhere('commission_type', TherapistCommission::TYPE_EARNING);
            $eligible = $appointment
                && $appointment->status === Appointment::STATUS_COMPLETED
                && $appointment->staff_profile_id
                && $transaction->payment_status === Transaction::PAYMENT_PAID;

            if (! $primary && ! $eligible) {
                return null;
            }

            $rate = (float) ($primary?->commission_rate ?? config('casa.commissions.therapist_rate', 0.22));
            $basis = (float) $transaction->amount;
            $desired = $eligible ? round($basis * $rate, 2) : 0.0;
            $earnedAt = $this->earnedAt($appointment, $transaction);

            if (! $primary) {
                return TherapistCommission::query()->create([
                    'staff_profile_id' => $appointment->staff_profile_id,
                    'appointment_id' => $appointment->id,
                    'transaction_id' => $transaction->id,
                    'primary_transaction_id' => $transaction->id,
                    'commission_type' => TherapistCommission::TYPE_EARNING,
                    'status' => TherapistCommission::STATUS_PENDING,
                    'basis_amount' => $basis,
                    'commission_rate' => $rate,
                    'commission_amount' => $desired,
                    'earned_at' => $earnedAt,
                ]);
            }

            if ($primary->status === TherapistCommission::STATUS_PENDING) {
                $primary->update([
                    'staff_profile_id' => $eligible ? $appointment->staff_profile_id : $primary->staff_profile_id,
                    'appointment_id' => $eligible ? $appointment->id : $primary->appointment_id,
                    'basis_amount' => $basis,
                    'commission_amount' => $desired,
                    'earned_at' => $earnedAt ?? $primary->earned_at,
                ]);

                return $primary->refresh();
            }

            $paidTotal = (float) $commissions
                ->where('status', TherapistCommission::STATUS_PAID)
                ->sum(fn (TherapistCommission $commission) => (float) $commission->commission_amount);
            $difference = round($desired - $paidTotal, 2);
            $pendingAdjustment = $commissions->first(fn (TherapistCommission $commission) => $commission->commission_type === TherapistCommission::TYPE_ADJUSTMENT
                && $commission->status === TherapistCommission::STATUS_PENDING);

            if ($difference === 0.0) {
                $pendingAdjustment?->delete();

                return $primary;
            }

            $attributes = [
                'staff_profile_id' => $primary->staff_profile_id,
                'appointment_id' => $primary->appointment_id,
                'transaction_id' => $transaction->id,
                'adjusts_commission_id' => $primary->id,
                'commission_type' => TherapistCommission::TYPE_ADJUSTMENT,
                'status' => TherapistCommission::STATUS_PENDING,
                'basis_amount' => $basis,
                'commission_rate' => $rate,
                'commission_amount' => $difference,
                'earned_at' => now(),
            ];

            if ($pendingAdjustment) {
                $pendingAdjustment->update($attributes);

                return $pendingAdjustment->refresh();
            }

            return TherapistCommission::query()->create($attributes);
        }, 3);
    }

    private function earnedAt(?Appointment $appointment, Transaction $transaction): ?Carbon
    {
        return collect([$appointment?->completed_at, $transaction->paid_at])
            ->filter()
            ->sortByDesc(fn (Carbon $date) => $date->getTimestamp())
            ->first();
    }
}
