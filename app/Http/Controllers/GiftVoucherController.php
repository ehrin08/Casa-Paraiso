<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\CustomerMembership;
use App\Models\CustomerProfile;
use App\Models\GiftVoucher;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Gift vouchers for admins and receptionists; only admins void vouchers or change voucher settings. */
class GiftVoucherController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'redeemed', 'expired', 'void'])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $today = CustomerMembership::businessDate();
        $expired = fn (Builder $query) => $query->where('status', GiftVoucher::STATUS_ACTIVE)->whereNotNull('expires_at')->whereDate('expires_at', '<', $today);

        $vouchers = GiftVoucher::query()
            ->with(['purchaser', 'redeemedTransaction'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', '%'.GiftVoucher::normalizeCode($search).'%')
                ->orWhere('purchaser_name', 'like', "%{$search}%")
                ->orWhereHas('purchaser', fn (Builder $customers) => $customers->search($search))))
            ->when($status === 'active', fn (Builder $query) => $query->redeemable())
            ->when($status === 'redeemed', fn (Builder $query) => $query->where('status', GiftVoucher::STATUS_REDEEMED))
            ->when($status === 'void', fn (Builder $query) => $query->where('status', GiftVoucher::STATUS_VOID))
            ->when($status === 'expired', $expired)
            ->latest('issued_at')->latest('id')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('gift-vouchers.index', [
            'vouchers' => $vouchers,
            'search' => $search,
            'status' => $status,
            'settings' => ApplicationSetting::current(),
            'summary' => [
                'outstanding' => (float) GiftVoucher::query()->redeemable()->sum('value'),
                'redeemed' => (float) GiftVoucher::query()->where('status', GiftVoucher::STATUS_REDEEMED)->sum('applied_amount'),
                'forfeited' => (float) GiftVoucher::query()->where('status', GiftVoucher::STATUS_REDEEMED)->sum('forfeited_amount'),
                'expired' => (float) $expired(GiftVoucher::query())->sum('value'),
            ],
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function create(Request $request): View
    {
        $settings = ApplicationSetting::current();

        return view('gift-vouchers.create', [
            'denominations' => $settings->giftVoucherDenominations(),
            'expiresAt' => $settings->gift_voucher_default_expiry_months
                ? Carbon::parse(CustomerMembership::businessDate())->addMonths($settings->gift_voucher_default_expiry_months)->toDateString()
                : null,
            'customers' => CustomerProfile::query()->with('user')->orderBy('id')->get(),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => filled($request->input('code')) ? GiftVoucher::normalizeCode((string) $request->input('code')) : null]);
        $data = $request->validate([
            'value' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'code' => ['nullable', 'string', 'max:60', 'regex:/^[A-Z0-9-]+$/', 'unique:gift_vouchers,code'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'purchaser_name' => ['nullable', 'string', 'max:255'],
            'purchaser_customer_profile_id' => ['nullable', 'integer', Rule::exists('customer_profiles', 'id')->whereNull('deleted_at')],
            'sale_payment_method' => ['required', Rule::in(Transaction::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['code.regex' => __('Use letters, numbers and dashes only, as printed on the voucher.')]);

        $voucher = GiftVoucher::query()->create([
            ...$data,
            'code' => $data['code'] ?? GiftVoucher::generateCode(),
            'status' => GiftVoucher::STATUS_ACTIVE,
            'issued_at' => now(),
            'sold_by' => $request->user()->id,
        ]);

        return to_route($this->routePrefix($request).'gift-vouchers.show', $voucher)->with('status', 'gift-voucher-issued');
    }

    public function show(Request $request, GiftVoucher $giftVoucher): View
    {
        $giftVoucher->load(['purchaser', 'seller', 'redeemedTransaction.customerProfile']);

        return view('gift-vouchers.show', [
            'voucher' => $giftVoucher,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function void(Request $request, GiftVoucher $giftVoucher): RedirectResponse
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:255']]);

        if ($giftVoucher->status !== GiftVoucher::STATUS_ACTIVE) {
            return back()->withErrors(['voucher' => __('Only an unredeemed voucher can be voided.')]);
        }

        $giftVoucher->forceFill([
            'status' => GiftVoucher::STATUS_VOID,
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $data['void_reason'],
        ])->save();

        return back()->with('status', 'gift-voucher-voided');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gift_voucher_denominations' => ['required', 'string', 'max:100', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
            'gift_voucher_default_expiry_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'gift_voucher_combinable' => ['sometimes', 'boolean'],
        ], ['gift_voucher_denominations.regex' => __('List amounts as whole numbers separated by commas, for example 300, 500, 1000.')]);
        $current = ApplicationSetting::current();

        ApplicationSetting::updateCurrent([
            ...($current->exists ? [] : ApplicationSetting::defaults()),
            'gift_voucher_denominations' => collect(explode(',', $data['gift_voucher_denominations']))->map(fn ($value) => (int) trim($value))->filter()->unique()->sort()->values()->all(),
            'gift_voucher_default_expiry_months' => $data['gift_voucher_default_expiry_months'] ?? null,
            'gift_voucher_combinable' => $request->boolean('gift_voucher_combinable'),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'gift-voucher-settings-updated');
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isReceptionist() ? 'reception.' : 'admin.';
    }
}
