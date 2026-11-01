<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\PaymentPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Web support endpoint that previews member benefits and gift vouchers on payment forms; saving always re-prices. */
class TransactionQuoteController extends Controller
{
    public function __invoke(Request $request, PaymentPricing $pricing): JsonResponse
    {
        $data = $request->validate([
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'customer_profile_id' => ['nullable', 'integer', 'exists:customer_profiles,id'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'paid_at' => ['nullable', 'date'],
            'gift_voucher_code' => ['nullable', 'string', 'max:60'],
        ]);
        $appointment = filled($data['appointment_id'] ?? null) ? Appointment::query()->findOrFail($data['appointment_id']) : null;
        $paidAt = filled($data['paid_at'] ?? null) ? Carbon::parse($data['paid_at']) : null;

        try {
            $quote = $pricing->quote($appointment, (float) $data['amount'], $paidAt, voucherCode: $data['gift_voucher_code'] ?? null, customerProfileId: $data['customer_profile_id'] ?? null);
        } catch (ValidationException $exception) {
            $quote = $pricing->quote($appointment, (float) $data['amount'], $paidAt, customerProfileId: $data['customer_profile_id'] ?? null);

            return response()->json([...self::present($quote), 'voucher_error' => $exception->validator->errors()->first('gift_voucher_code')]);
        }

        return response()->json(self::present($quote));
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public static function present(array $quote): array
    {
        $membership = $quote['membership'];

        return [
            'membership' => $membership ? $membership->tier->name.' · '.$membership->card_number : null,
            'adjustments' => array_map(fn (array $adjustment) => ['label' => $adjustment['label'], 'amount' => $adjustment['amount']], $quote['adjustments']),
            'gross' => $quote['gross'],
            'discount' => $quote['discount'],
            'net' => $quote['net'],
            'voucher' => $quote['voucher'] ? ['code' => $quote['voucher']->code, 'value' => (float) $quote['voucher']->value, 'applied' => $quote['voucher_applied'], 'forfeited' => $quote['voucher_forfeited']] : null,
            'amount_due' => $quote['amount_due'],
            'voucher_error' => null,
        ];
    }
}
