<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $sorts = [
            'name' => 'customer_display_name',
            'code' => 'customer_profiles.customer_code',
            'appointments' => 'appointments_count',
            'feedback' => 'feedback_count',
            'preference' => 'customer_profiles.contact_preference',
        ];
        $sort = $this->indexSort($request, $sorts, 'name');
        $direction = $this->indexDirection($request);

        $customers = CustomerProfile::query()
            ->with('user')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->leftJoin('users', 'users.id', '=', 'customer_profiles.user_id')
            ->select('customer_profiles.*')
            ->selectRaw(CustomerProfile::displayNameSql().' as customer_display_name')
            ->withCount(['appointments', 'feedback'])
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('customer_display_name')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('staff.customers.index', [
            'customers' => $customers,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(CustomerProfile $customer): View
    {
        $customer->load([
            'user',
            'appointments' => fn ($query) => $query
                ->with(['service', 'staffProfile.user'])
                ->latest('requested_start_at')
                ->limit(10),
            'feedback' => fn ($query) => $query
                ->with(['service', 'appointment'])
                ->latest('submitted_at')
                ->limit(8),
        ])->loadCount(['appointments', 'feedback']);

        return view('staff.customers.show', [
            'customer' => $customer,
        ]);
    }
}
