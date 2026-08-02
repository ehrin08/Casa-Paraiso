<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentCompletion
{
    public function __construct(
        private readonly AppointmentWorkflow $workflow,
        private readonly TransactionNumber $transactionNumbers,
        private readonly TherapistCommissionSynchronizer $commissions,
        private readonly RfmPromotionGenerator $promotions,
    ) {}

    /** @param array<string, mixed> $payment */
    public function complete(Appointment $appointment, array $payment, int $adminId): Transaction
    {
        return DB::transaction(function () use ($appointment, $payment, $adminId): Transaction {
            $locked = Appointment::query()->with('transactions')->lockForUpdate()->findOrFail($appointment->id);

            if ($locked->status !== Appointment::STATUS_CONFIRMED) {
                throw ValidationException::withMessages(['status' => __('Only a confirmed appointment can be finished.')]);
            }

            if (! $locked->scheduled_start_at || $locked->scheduled_start_at->isFuture()) {
                throw ValidationException::withMessages(['status' => __('This service can be finished once its scheduled start time is reached.')]);
            }

            if ($locked->transactions->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => __('This appointment already has a transaction and cannot be finished again.')]);
            }

            $received = in_array($payment['payment_status'], Transaction::PAYMENT_RECEIVED_STATUSES, true);
            $transaction = $this->transactionNumbers->create([
                'customer_profile_id' => $locked->customer_profile_id,
                'appointment_id' => $locked->id,
                'service_id' => $locked->service_id,
                'amount' => $payment['amount'],
                'payment_status' => $payment['payment_status'],
                'payment_method' => $received ? $payment['payment_method'] : null,
                'paid_at' => $received ? Carbon::parse($payment['paid_at']) : null,
                'recorded_by' => $adminId,
                'notes' => $payment['notes'] ?? null,
            ]);

            $this->workflow->changeStatus($locked, Appointment::STATUS_COMPLETED, $adminId, __('Service finished and transaction recorded'));

            $this->commissions->synchronize($transaction);
            if ($transaction->payment_status === Transaction::PAYMENT_PAID) {
                $this->promotions->generateForTransaction($transaction);
            }

            return $transaction;
        }, 3);
    }
}
