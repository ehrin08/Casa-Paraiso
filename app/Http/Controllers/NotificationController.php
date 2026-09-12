<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Services\BookingNotificationState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()
                ->notifications()
                ->latest()
                ->paginate((int) config('casa.pagination.per_page', 15))
                ->withQueryString(),
        ]);
    }

    public function open(Request $request, string $notification, BookingNotificationState $state): RedirectResponse
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();
        $state->notifyChanged($request->user());

        $appointment = Appointment::query()->findOrFail($notification->data['appointment_id'] ?? 0);

        return redirect()->route($this->appointmentRoute($request->user()), $appointment);
    }

    public function markAllRead(Request $request, BookingNotificationState $state): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        $state->notifyChanged($request->user());

        return redirect()->route('notifications.index');
    }

    public function bookingCount(Request $request, BookingNotificationState $state): \Illuminate\Http\JsonResponse
    {
        return response()->json(['count' => $state->unreadCount($request->user())]);
    }

    public function acknowledgeBookings(Request $request, BookingNotificationState $state): \Illuminate\Http\JsonResponse
    {
        return response()->json(['count' => $state->acknowledge($request->user())]);
    }

    private function appointmentRoute(User $user): string
    {
        return match (true) {
            $user->isStaff() => 'staff.appointments.show',
            $user->isReceptionist() => 'reception.appointments.show',
            default => 'admin.appointments.show',
        };
    }
}
