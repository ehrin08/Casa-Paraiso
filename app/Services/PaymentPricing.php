<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\AppointmentAddon;
use App\Models\CustomerMembership;
use App\Models\GiftVoucher;
use App\Models\MembershipPerkUsage;
use App\Models\MembershipReferralCredit;
use App\Models\Transaction;
use App\Models\TransactionAdjustment;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Prices a paid visit against membership benefits and an optional gift voucher.
 *
 * The amount staff enter is the gross charge. Member benefits are applied line by line in a
 * fixed order (VIP Room price, birthday treat, add-on percentage, service percentage, then one
 * referral credit) and the transaction amount becomes the net service revenue. A gift voucher
 * is a prepaid tender, not a discount: it pays part or all of the net, any excess is due in
 * another method, and an unused balance is forfeited.
 */
class PaymentPricing
{
    /**
     * @return array{gross: float, membership: ?CustomerMembership, adjustments: list<array{type: string, label: string, amount: float}>, discount: float, net: float, referral_credit: ?MembershipReferralCredit, birthday: bool, voucher: ?GiftVoucher, voucher_applied: float, voucher_forfeited: float, amount_due: float}
     */
    public function quote(?Appointment $appointment, float $gross, ?CarbonInterface $paidAt = null, bool $lock = false, ?string $voucherCode = null, ?int $customerProfileId = null): array
    {
        $paidAt ??= now();
        $appointment?->loadMissing(['service', 'addons', 'customerProfile']);
        $customerProfileId = $appointment?->customer_profile_id ?? $customerProfileId;
        $voucher = filled($voucherCode) ? $this->voucher($voucherCode, $lock) : null;
        $combinable = ApplicationSetting::current()->gift_voucher_combinable;

        if ($voucher && ! $combinable && $appointment?->promotion_suggestion_id) {
            throw ValidationException::withMessages(['gift_voucher_code' => __('This booking already uses a customer reward. Gift vouchers cannot be combined with other promos.')]);
        }

        $memberBenefits = $appointment && $customerProfileId && (! $voucher || $combinable);
        $membership = $memberBenefits
            ? CustomerMembership::query()->where('customer_profile_id', $customerProfileId)->activeOn($paidAt)->with(['tier', 'customerProfile'])->latest('valid_until')->first()
            : null;
        [$adjustments, $birthday] = $membership ? $this->memberAdjustments($appointment, $membership, $paidAt, $lock) : [[], false];

        $discount = min($gross, round(array_sum(array_column($adjustments, 'amount')), 2));
        $credit = $memberBenefits ? $this->referralCredit($customerProfileId, $lock) : null;

        if ($credit && $gross - $discount > 0) {
            $value = min((float) $credit->amount, $gross - $discount);
            $adjustments[] = $this->adjustment(TransactionAdjustment::TYPE_REFERRAL, __('Referral reward'), $value);
            $discount = round($discount + $value, 2);
        } else {
            $credit = null;
        }

        $net = round(max(0, $gross - $discount), 2);
        $applied = $voucher ? min((float) $voucher->value, $net) : 0.0;

        return [
            'gross' => round($gross, 2),
            'membership' => $membership,
            'adjustments' => $adjustments,
            'discount' => $discount,
            'net' => $net,
            'referral_credit' => $credit,
            'birthday' => $birthday,
            'voucher' => $voucher,
            'voucher_applied' => round($applied, 2),
            'voucher_forfeited' => $voucher ? round((float) $voucher->value - $applied, 2) : 0.0,
            'amount_due' => round($net - $applied, 2),
        ];
    }

    /**
     * Applies benefits and the voucher once, when a transaction is first recorded as paid. Later
     * edits keep the stored breakdown and only re-derive the net from the edited gross amount.
     */
    public function settle(Transaction $transaction, float $gross, bool $becamePaid, ?string $voucherCode = null): Transaction
    {
        $paid = $becamePaid && $transaction->payment_status === Transaction::PAYMENT_PAID;

        if (filled($voucherCode) && ! $paid) {
            throw ValidationException::withMessages(['gift_voucher_code' => __('A gift voucher can only be redeemed when the payment is first recorded as paid.')]);
        }

        if ($transaction->gift_voucher_id || $transaction->adjustments()->exists()) {
            $net = round(max(0, $gross - (float) $transaction->discount_amount), 2);
            $transaction->forceFill([
                'gross_amount' => $gross,
                'amount' => $net,
                'amount_due' => $transaction->gift_voucher_id ? round(max(0, $net - (float) $transaction->gift_voucher_applied), 2) : $transaction->amount_due,
            ])->save();

            return $transaction;
        }

        $appointment = $transaction->appointment;

        if (! $paid || (! $appointment && ! filled($voucherCode))) {
            $transaction->forceFill(['gross_amount' => $appointment ? $gross : null, 'discount_amount' => 0, 'amount' => $gross])->save();

            return $transaction;
        }

        $quote = $this->quote($appointment, $gross, $transaction->paid_at, lock: true, voucherCode: $voucherCode, customerProfileId: $transaction->customer_profile_id);
        $paidAt = $transaction->paid_at ?? now();

        foreach ($quote['adjustments'] as $adjustment) {
            $transaction->adjustments()->create($adjustment);
        }

        $quote['referral_credit']?->forceFill(['used_transaction_id' => $transaction->id, 'used_at' => $paidAt])->save();

        if ($quote['birthday']) {
            MembershipPerkUsage::query()->create([
                'customer_membership_id' => $quote['membership']->id,
                'transaction_id' => $transaction->id,
                'perk' => MembershipPerkUsage::PERK_BIRTHDAY,
                'amount' => collect($quote['adjustments'])->firstWhere('type', TransactionAdjustment::TYPE_BIRTHDAY)['amount'] ?? 0,
                'used_at' => $paidAt,
            ]);
        }

        $quote['voucher']?->forceFill([
            'status' => GiftVoucher::STATUS_REDEEMED,
            'redeemed_transaction_id' => $transaction->id,
            'redeemed_at' => $paidAt,
            'applied_amount' => $quote['voucher_applied'],
            'forfeited_amount' => $quote['voucher_forfeited'],
        ])->save();

        $transaction->forceFill([
            'gross_amount' => $quote['gross'],
            'discount_amount' => $quote['discount'],
            'customer_membership_id' => $quote['membership']?->id,
            'gift_voucher_id' => $quote['voucher']?->id,
            'gift_voucher_applied' => $quote['voucher_applied'],
            'amount_due' => $quote['voucher'] ? $quote['amount_due'] : null,
            'amount' => $quote['net'],
        ])->save();

        return $transaction;
    }

    /** @return array{0: list<array{type: string, label: string, amount: float}>, 1: bool} */
    private function memberAdjustments(Appointment $appointment, CustomerMembership $membership, CarbonInterface $paidAt, bool $lock): array
    {
        $tier = $membership->tier;
        $prefix = __(':tier member', ['tier' => $tier->name]);
        $adjustments = [];
        $birthday = false;
        $servicePrice = (float) ($appointment->service?->price ?? 0);
        $addonLines = $appointment->addons->map(fn (AppointmentAddon $addon) => ['code' => $addon->addon_code, 'name' => $addon->addon_name, 'price' => (float) $addon->price])->all();

        foreach ($addonLines as $index => $line) {
            if ($line['code'] === 'vip-room' && $tier->vip_room_price !== null && $line['price'] > (float) $tier->vip_room_price) {
                $discount = $line['price'] - (float) $tier->vip_room_price;
                $adjustments[] = $this->adjustment(TransactionAdjustment::TYPE_VIP_ROOM, __(':prefix: :addon for PHP :price', ['prefix' => $prefix, 'addon' => $line['name'], 'price' => number_format((float) $tier->vip_room_price, 2)]), $discount);
                $addonLines[$index]['price'] -= $discount;
            }
        }

        if ($this->birthdayEligible($membership, $paidAt, $lock)) {
            $label = __('Birthday treat: :treat', ['treat' => $tier->birthday_treat_label ?: __('Birthday treat')]);

            if ($tier->birthday_treat_addon_code) {
                foreach ($addonLines as $index => $line) {
                    if ($line['code'] === $tier->birthday_treat_addon_code && $line['price'] > 0) {
                        $adjustments[] = $this->adjustment(TransactionAdjustment::TYPE_BIRTHDAY, $label, $line['price']);
                        $addonLines[$index]['price'] = 0.0;
                        $birthday = true;
                        break;
                    }
                }
            } elseif ($tier->birthday_treat_service_id && $servicePrice > 0) {
                // A service treat is worth up to that service's price off the booked service.
                $value = min($servicePrice, (float) ($tier->birthdayTreatService?->price ?? 0));

                if ($value > 0) {
                    $adjustments[] = $this->adjustment(TransactionAdjustment::TYPE_BIRTHDAY, $label, $value);
                    $servicePrice -= $value;
                    $birthday = true;
                }
            }
        }

        $addonPercent = (float) $tier->addon_discount_percent;
        foreach ($addonLines as $line) {
            if ($addonPercent > 0 && $line['price'] > 0 && in_array($line['code'], $tier->addon_discount_codes ?? [], true)) {
                $adjustments[] = $this->adjustment(TransactionAdjustment::TYPE_ADDON_DISCOUNT, __(':prefix: :percent% off :addon', ['prefix' => $prefix, 'percent' => $this->percent($addonPercent), 'addon' => $line['name']]), $line['price'] * $addonPercent / 100);
            }
        }

        $servicePercent = (float) $tier->service_discount_percent;
        if ($servicePercent > 0 && $servicePrice > 0) {
            $adjustments[] = $this->adjustment(TransactionAdjustment::TYPE_SERVICE_DISCOUNT, __(':prefix: :percent% off :service', ['prefix' => $prefix, 'percent' => $this->percent($servicePercent), 'service' => $appointment->service?->name ?? __('service')]), $servicePrice * $servicePercent / 100);
        }

        return [$adjustments, $birthday];
    }

    private function voucher(string $code, bool $lock): GiftVoucher
    {
        $voucher = GiftVoucher::query()
            ->where('code', GiftVoucher::normalizeCode($code))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        $message = match (true) {
            ! $voucher => __('No gift voucher has this code.'),
            $voucher->status === GiftVoucher::STATUS_REDEEMED => __('This gift voucher was already redeemed. Vouchers are for one-time use only.'),
            $voucher->status === GiftVoucher::STATUS_VOID => __('This gift voucher was voided.'),
            ! $voucher->isRedeemable() => __('This gift voucher expired on :date.', ['date' => $voucher->expires_at->format('M d, Y')]),
            default => null,
        };

        if ($message) {
            throw ValidationException::withMessages(['gift_voucher_code' => $message]);
        }

        return $voucher;
    }

    private function birthdayEligible(CustomerMembership $membership, CarbonInterface $paidAt, bool $lock): bool
    {
        $birthDate = $membership->customerProfile?->birth_date;
        $manila = $paidAt->copy()->timezone('Asia/Manila');

        if (! $birthDate || (int) $birthDate->month !== (int) $manila->month) {
            return false;
        }

        // One birthday treat per membership per calendar year.
        return ! MembershipPerkUsage::query()
            ->where('customer_membership_id', $membership->id)
            ->where('perk', MembershipPerkUsage::PERK_BIRTHDAY)
            ->whereYear('used_at', $manila->year)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();
    }

    private function referralCredit(int $customerProfileId, bool $lock): ?MembershipReferralCredit
    {
        return MembershipReferralCredit::query()
            ->where('customer_profile_id', $customerProfileId)
            ->whereNull('used_at')
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /** @return array{type: string, label: string, amount: float} */
    private function adjustment(string $type, string $label, float $amount): array
    {
        return ['type' => $type, 'label' => $label, 'amount' => round($amount, 2)];
    }

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
