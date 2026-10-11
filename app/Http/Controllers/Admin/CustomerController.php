<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerNoteRequest;
use App\Models\CustomerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $sorts = [
            'name' => 'customer_display_name',
            'code' => 'customer_profiles.customer_code',
            'appointments' => 'appointments_count',
            'transactions' => 'transactions_count',
            'feedback' => 'feedback_count',
            'preference' => 'customer_profiles.contact_preference',
            'status' => 'users.is_active',
        ];
        $sort = $this->indexSort($request, $sorts, 'name');
        $direction = $this->indexDirection($request);

        $customers = CustomerProfile::query()
            ->with('user')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->leftJoin('users', 'users.id', '=', 'customer_profiles.user_id')
            ->select('customer_profiles.*')
            ->selectRaw(CustomerProfile::displayNameSql().' as customer_display_name')
            ->withCount(['appointments', 'transactions', 'feedback', 'promotionSuggestions'])
            ->when($status === 'active', fn ($query) => $query->where('users.is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('users.is_active', false))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('customer_display_name')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        $modalCustomerId = $request->session()->pull('customer_modal_id');
        $modalCustomer = $modalCustomerId ? CustomerProfile::find($modalCustomerId) : null;
        if ($modalCustomer) {
            $this->loadDetails($modalCustomer);
        }

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'totalCustomers' => CustomerProfile::query()->count(),
            'modalCustomer' => $modalCustomer,
        ]);
    }

    public function show(Request $request, CustomerProfile $customer): View
    {
        $this->loadDetails($customer);
        $isModal = $request->header('X-Casa-Panel') === '1' && $request->query('modal') === '1';

        return view($isModal ? 'admin.customers.modal' : 'admin.customers.show', [
            'customer' => $customer,
            'returnFilters' => $request->query('return_filters', []),
        ]);
    }

    private function loadDetails(CustomerProfile $customer): void
    {
        $customer->load([
            'user',
            'appointments' => fn ($query) => $query
                ->with(['service', 'staffProfile.user'])
                ->latest('requested_start_at')
                ->limit(8),
            'transactions' => fn ($query) => $query
                ->with(['service', 'appointment'])
                ->latest()
                ->limit(8),
            'feedback' => fn ($query) => $query
                ->with(['service', 'appointment'])
                ->latest('submitted_at')
                ->limit(8),
            'promotionSuggestions' => fn ($query) => $query
                ->with(['rfmSegment', 'promotionRule'])
                ->latest()
                ->limit(8),
        ])->loadCount(['appointments', 'transactions', 'feedback', 'promotionSuggestions']);
    }

    public function update(CustomerNoteRequest $request, CustomerProfile $customer): RedirectResponse
    {
        $customer->update($request->safe()->only(['notes']));

        if ($request->validated('return_to') === 'index') {
            return redirect()->route('admin.customers.index', $request->returnQuery())
                ->with('customer_modal_id', $customer->id)
                ->with('status', 'customer-updated');
        }

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'customer-updated');
    }
}
