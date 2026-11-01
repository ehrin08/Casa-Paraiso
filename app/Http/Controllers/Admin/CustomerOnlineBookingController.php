<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerOnlineBookingBlockRequest;
use App\Models\CustomerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerOnlineBookingController extends Controller
{
    public function block(CustomerOnlineBookingBlockRequest $request, CustomerProfile $customer): RedirectResponse
    {
        $customer->update([
            'online_booking_blocked_at' => now(),
            'online_booking_block_reason' => trim((string) $request->validated('reason')),
            'online_booking_blocked_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.customers.show', $customer)->with('status', 'customer-online-booking-blocked');
    }

    public function unblock(Request $request, CustomerProfile $customer): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $customer->update([
            'online_booking_blocked_at' => null,
            'online_booking_block_reason' => null,
            'online_booking_blocked_by' => null,
        ]);

        return redirect()->route('admin.customers.show', $customer)->with('status', 'customer-online-booking-unblocked');
    }
}
