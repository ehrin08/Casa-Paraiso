<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Transaction;
use Illuminate\Http\Request;

class AppointmentCompletionConfirmation
{
    public const FLASH_KEY = 'completed_transaction_id';

    public function consume(Request $request): ?Transaction
    {
        $id = $request->session()->pull(self::FLASH_KEY);
        $user = $request->user();

        if (! is_numeric($id) || ! ($user?->isAdmin() || $user?->isReceptionist())) {
            return null;
        }

        return Transaction::query()
            ->whereKey($id)
            ->where('recorded_by', $user->id)
            ->whereHas('appointment', fn ($query) => $query->where('status', Appointment::STATUS_COMPLETED))
            ->with([
                'recorder',
                'appointment.customerProfile.user',
                'appointment.service',
                'appointment.staffProfile.user',
                'appointment.addons',
                'appointment.promotionSuggestion',
            ])
            ->first();
    }
}
