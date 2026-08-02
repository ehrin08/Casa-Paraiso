<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionWorkflow
{
    public function __construct(
        private readonly TransactionNumber $numbers,
        private readonly TherapistCommissionSynchronizer $commissions,
        private readonly RfmPromotionGenerator $promotions,
    ) {}

    /** @param array<string, mixed> $data */
    public function persist(Transaction $transaction, array $data, int $actorId): Transaction
    {
        return DB::transaction(function () use ($actorId, $data, $transaction): Transaction {
            $wasPaid = $transaction->exists && $transaction->payment_status === Transaction::PAYMENT_PAID;
            if (! empty($data['appointment_id'])) {
                $appointment = Appointment::query()->findOrFail($data['appointment_id']);
                $data['customer_profile_id'] = $appointment->customer_profile_id;
                $data['service_id'] = $appointment->service_id;
            }

            $paymentReceived = in_array($data['payment_status'], Transaction::PAYMENT_RECEIVED_STATUSES, true);
            $attributes = [
                'customer_profile_id' => $data['customer_profile_id'],
                'appointment_id' => $data['appointment_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'amount' => $data['amount'],
                'payment_status' => $data['payment_status'],
                'payment_method' => $paymentReceived ? $data['payment_method'] : null,
                'paid_at' => $paymentReceived ? Carbon::parse($data['paid_at']) : null,
                'recorded_by' => $transaction->recorded_by ?: $actorId,
                'notes' => $data['notes'] ?? null,
            ];

            if ($transaction->exists) {
                $transaction->update($attributes);
            } else {
                $transaction = $this->numbers->create($attributes);
            }

            $this->commissions->synchronize($transaction);

            if (! $wasPaid && $transaction->payment_status === Transaction::PAYMENT_PAID) {
                $this->promotions->generateForTransaction($transaction);
            }

            return $transaction->refresh();
        }, 3);
    }
}
