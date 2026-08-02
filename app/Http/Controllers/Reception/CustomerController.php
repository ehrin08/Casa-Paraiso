<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reception\CustomerUpdateRequest;
use App\Models\CustomerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('reception.customers.index', [
            'search' => $search,
            'customers' => CustomerProfile::query()->with('user')->withCount(['appointments', 'transactions'])
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('customer_code', 'like', "%{$search}%")->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
                ->orderBy('customer_code')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString(),
        ]);
    }

    public function show(CustomerProfile $customer): View
    {
        $customer->load(['user', 'appointments' => fn ($query) => $query->with(['service', 'staffProfile.user'])->latest('scheduled_start_at')->limit(12), 'transactions' => fn ($query) => $query->with(['service', 'appointment'])->latest()->limit(12)]);

        return view('reception.customers.show', ['customer' => $customer]);
    }

    public function update(CustomerUpdateRequest $request, CustomerProfile $customer): RedirectResponse
    {
        $data = $request->validated();
        $customer->user->update(['phone' => $data['phone'] ?? null]);
        $customer->update(['address' => $data['address'] ?? null, 'contact_preference' => $data['contact_preference'] ?? null, 'notes' => $data['notes'] ?? null]);

        return redirect()->route('reception.customers.show', $customer)->with('status', 'customer-updated');
    }
}
