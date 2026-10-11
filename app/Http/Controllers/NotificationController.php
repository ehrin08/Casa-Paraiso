<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationIndexRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Services\BookingNotificationState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(NotificationIndexRequest $request): View
    {
        $validated = $request->validated();
        $filters = ['q' => trim($validated['q'] ?? ''), 'status' => $validated['status'] ?? 'all'];
        $query = $request->user()->notifications()
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($query) use ($filters): void {
                foreach (['customer_name', 'service_name', 'appointment_number'] as $field) {
                    $query->orWhere('data->'.$field, 'like', '%'.$filters['q'].'%');
                }
            }));
        $counts = ['all' => (clone $query)->count(), 'unread' => (clone $query)->whereNull('read_at')->count()];
        $notifications = $query
            ->when($filters['status'] === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()->orderByDesc('id')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->appends(array_filter($filters, fn ($value) => $value !== ''));
        $appointments = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->whereIn('id', $notifications->getCollection()->pluck('data.appointment_id')->filter()->unique())
            ->when($request->user()->isStaff(), fn ($query) => $query->where('staff_profile_id', $request->user()->staffProfile?->id ?? 0))
            ->get()->keyBy('id');
        $selectedNotification = $notifications->firstWhere('id', $validated['selected'] ?? null) ?? $notifications->first();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filters' => $filters,
            'counts' => $counts,
            'appointments' => $appointments,
            'selectedNotification' => $selectedNotification,
            'selectedAppointment' => $appointments->get($selectedNotification?->data['appointment_id'] ?? 0),
            'calendarRoute' => str_replace('.show', '.index', $this->appointmentRoute($request->user())),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()->notifications()->findOrFail($notification);
        $appointment = Appointment::query()->findOrFail($notification->data['appointment_id'] ?? 0);
        if ($request->user()->isStaff()) {
            $staffProfile = $request->user()->staffProfile;
            abort_unless($staffProfile && (int) $appointment->staff_profile_id === (int) $staffProfile->id, 403);
        }
        $notification->markAsRead();

        return redirect()->route($this->appointmentRoute($request->user()), $appointment);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notifications.index');
    }

    public function bookingCount(Request $request, BookingNotificationState $state): JsonResponse
    {
        return response()->json(['count' => $state->unreadCount($request->user())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function acknowledgeBookings(Request $request, BookingNotificationState $state): JsonResponse
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
