<?php

namespace App\Http\Controllers;

use App\Models\CustomerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalCustomerLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:255']]);
        $customers = CustomerProfile::query()->with('user')
            ->search(trim($data['q']))
            ->leftJoin('users', 'customer_profiles.user_id', '=', 'users.id')
            ->select('customer_profiles.*')
            ->orderByRaw(CustomerProfile::displayNameSql())
            ->orderBy('customer_profiles.id')
            ->limit(15)->get()
            ->map(fn (CustomerProfile $customer) => [
                'id' => $customer->id,
                'name' => $customer->display_name,
                'phone' => $customer->display_phone,
                'customer_code' => $customer->customer_code,
                'is_guest' => $customer->is_guest,
            ]);

        return response()->json(['customers' => $customers])->header('Cache-Control', 'private, no-store');
    }
}
