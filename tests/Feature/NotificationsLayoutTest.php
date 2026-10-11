<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NewCustomerBookingNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private StaffProfile $therapist;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->therapist = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();
        $customer->user->update(['name' => 'Isabel Cruz', 'phone' => '+63 917 123 4567']);
        $this->appointment = Appointment::factory()->create([
            'customer_profile_id' => $customer->id,
            'service_id' => Service::factory()->create(['name' => 'Signature massage'])->id,
            'staff_profile_id' => $this->therapist->id,
            'scheduled_start_at' => '2026-11-04 23:00:00',
            'scheduled_end_at' => '2026-11-05 00:00:00',
            'customer_notes' => '<script>Looking forward to this!</script>',
        ]);
    }

    public function test_all_operational_roles_preview_current_details_without_reading_the_alert(): void
    {
        foreach ([$this->admin, User::factory()->create(['role' => 'super_admin']), User::factory()->receptionist()->create(), $this->therapist->user] as $user) {
            $notification = $this->alert($user);
            $calendarRoute = $user->isStaff() ? 'staff.appointments.index' : ($user->isReceptionist() ? 'reception.appointments.index' : 'admin.appointments.index');
            $response = $this->actingAs($user)->get(route('notifications.index', ['selected' => $notification->id], false))
                ->assertOk()->assertSee('data-notifications-workspace', false)->assertSee('Customer booking alerts')
                ->assertSee('All (1)')->assertSee('Unread (1)')->assertSee('Isabel Cruz')
                ->assertSee($this->appointment->customerProfile->display_email)->assertSee('+63 917 123 4567')
                ->assertSee('60 minutes')->assertSee('11:00 PM')->assertSee('Nov 5, 12:00 AM')
                ->assertSee('<script>Looking forward to this!</script>')->assertDontSee('<script>Looking forward to this!</script>', false)
                ->assertSee(route($calendarRoute, ['calendar_date' => '2026-11-04', 'mode' => 'bookings']).'#booking-calendar')
                ->assertSee('Booking notifications')->assertSee('name="_method" value="PATCH"', false);
            $this->assertSame($notification->id, $response->viewData('selectedNotification')->id);
            $this->assertNull($notification->fresh()->read_at);
            $showRoute = str_replace('.index', '.show', $calendarRoute);
            $this->patch(route('notifications.open', $notification, false))->assertRedirect(route($showRoute, $this->appointment, false));
            $this->assertNotNull($notification->fresh()->read_at);
        }
    }

    public function test_search_counts_span_pages_and_unread_filter_and_use_retained_snapshots(): void
    {
        for ($index = 0; $index < 17; $index++) {
            $this->alert($this->admin, ['customer_name' => 'Snapshot customer', 'service_name' => 'Snapshot treatment', 'appointment_number' => 'SNAPSHOT-'.$index], $index === 16);
        }
        $this->alert($this->admin);
        $this->alert(User::factory()->admin()->create(), ['customer_name' => 'Snapshot customer']);
        $this->appointment->service->update(['name' => 'Renamed treatment']);
        foreach (['Snapshot customer', 'Snapshot treatment', 'SNAPSHOT-'] as $search) {
            foreach ([1, 2] as $page) {
                $this->actingAs($this->admin)->get(route('notifications.index', ['q' => $search, 'page' => $page, 'per_page' => 100], false))
                    ->assertOk()->assertViewHas('counts', ['all' => 17, 'unread' => 16])
                    ->assertViewHas('notifications', fn ($rows) => $rows->perPage() === 15 && $rows->total() === 17 && $rows->count() === ($page === 1 ? 15 : 2));
            }
        }
        $this->get(route('notifications.index', ['q' => 'Snapshot', 'status' => 'unread'], false))
            ->assertOk()->assertViewHas('counts', ['all' => 17, 'unread' => 16])
            ->assertViewHas('notifications', fn ($rows) => $rows->total() === 16 && $rows->every(fn ($row) => $row->read_at === null))
            ->assertSee('Renamed treatment');
        $this->assertSame(17, $this->admin->unreadNotifications()->count());
    }

    public function test_selection_is_recipient_scoped_and_current_page_only_and_links_reset_it(): void
    {
        $records = collect();
        for ($index = 0; $index < 16; $index++) {
            $records->push($this->alert($this->admin, ['customer_name' => 'Searchable customer'], createdAt: now()->addMinutes($index)));
        }
        $foreign = $this->alert(User::factory()->admin()->create());
        $selected = $records[5];
        $response = $this->actingAs($this->admin)->get(route('notifications.index', ['q' => 'Searchable', 'status' => 'all', 'selected' => $selected->id, 'per_page' => 100], false))
            ->assertOk()->assertViewHas('selectedNotification', fn ($row) => $row->id === $selected->id);
        parse_str((string) parse_url($response->viewData('notifications')->url(2), PHP_URL_QUERY), $nextPage);
        $this->assertSame(['q' => 'Searchable', 'status' => 'all', 'page' => '2'], $nextPage);
        foreach ([$records->first()->id, $foreign->id, (string) Str::uuid()] as $id) {
            $this->get(route('notifications.index', ['selected' => $id], false))->assertOk()
                ->assertViewHas('selectedNotification', fn ($row) => $row->id === $records->last()->id);
        }
        $this->get(route('notifications.index', ['page' => 2, 'selected' => $selected->id], false))->assertOk()
            ->assertViewHas('selectedNotification', fn ($row) => $row->id === $records->first()->id);
        $this->patch(route('notifications.open', $foreign, false))->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);
        $this->getJson(route('notifications.index', ['q' => str_repeat('x', 151), 'status' => 'invalid', 'selected' => 'invalid', 'page' => -1], false))
            ->assertUnprocessable()->assertJsonValidationErrors(['q', 'status', 'selected', 'page']);
    }

    public function test_reassigned_or_missing_bookings_cannot_expose_contacts_notes_or_be_marked_read_by_open(): void
    {
        $notification = $this->alert($this->therapist->user);
        $this->appointment->update(['staff_profile_id' => StaffProfile::factory()->create()->id]);
        $this->actingAs($this->therapist->user)->get(route('notifications.index', absolute: false))
            ->assertOk()->assertSee('This booking is unavailable')->assertViewHas('selectedAppointment', null)
            ->assertDontSee($this->appointment->customerProfile->display_email)->assertDontSee('+63 917 123 4567')
            ->assertDontSee('Looking forward to this!')->assertDontSee('action="'.route('notifications.open', $notification).'"', false);
        $this->patch(route('notifications.open', $notification, false))->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
        $missing = $this->alert($this->admin, ['appointment_id' => 999999]);
        $this->actingAs($this->admin)->get(route('notifications.index', absolute: false))->assertOk()
            ->assertViewHas('selectedAppointment', null)->assertSee('disabled', false);
        $this->patch(route('notifications.open', $missing, false))->assertNotFound();
        $this->assertNull($missing->fresh()->read_at);
    }

    public function test_empty_no_match_and_caught_up_states_and_bulk_read_remain_available(): void
    {
        $this->actingAs($this->admin)->get(route('notifications.index', absolute: false))->assertOk()
            ->assertSee('No notifications yet')->assertViewHas('selectedNotification', null)->assertDontSee('data-pagination', false);
        $this->alert($this->admin);
        $other = $this->alert(User::factory()->admin()->create());
        $this->get(route('notifications.index', ['q' => 'No matching value'], false))->assertOk()
            ->assertSee('No matching notifications')->assertSee('Clear search')->assertViewHas('counts', ['all' => 0, 'unread' => 0]);
        $this->patch(route('notifications.read-all', absolute: false))->assertRedirect(route('notifications.index', absolute: false));
        $this->get(route('notifications.index', ['status' => 'unread'], false))->assertOk()->assertSee('No unread notifications');
        $this->assertNull($other->fresh()->read_at);
        $this->actingAs(User::factory()->customer()->create())->get(route('notifications.index', absolute: false))->assertForbidden();
    }

    public function test_a_therapist_without_a_profile_cannot_open_an_unassigned_booking(): void
    {
        $user = User::factory()->staff()->create();
        $this->appointment->update(['staff_profile_id' => null]);
        $notification = $this->alert($user);
        $this->actingAs($user)->get(route('notifications.index', absolute: false))->assertOk()
            ->assertViewHas('selectedAppointment', null)->assertDontSee('Looking forward to this!');
        $this->patch(route('notifications.open', $notification, false))->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_calendar_date_opens_the_containing_week_for_each_role_and_validates_dates(): void
    {
        $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 11)->setTime(14, 0));
        foreach ([$this->admin, User::factory()->create(['role' => 'super_admin']), User::factory()->receptionist()->create(), $this->therapist->user] as $user) {
            $route = $user->isStaff() ? 'staff.appointments.index' : ($user->isReceptionist() ? 'reception.appointments.index' : 'admin.appointments.index');
            foreach (['2026-11-04', '2026-10-11'] as $date) {
                $week = Carbon::parse($date, 'Asia/Manila')->startOfWeek($user->isAdmin() ? Carbon::MONDAY : Carbon::SUNDAY)->toDateString();
                $this->actingAs($user)->get(route($route, ['calendar_date' => $date, 'mode' => 'bookings'], false))
                    ->assertOk()->assertViewHas('initialDate', $date)->assertViewHas('initialWeek', $week)
                    ->assertSee('initialDate:')->assertSee($date)->assertSee('id="booking-calendar"', false);
            }
            $this->get(route($route, absolute: false))->assertOk()->assertViewHas('initialDate', null);
            $this->getJson(route($route, ['calendar_date' => '2026-02-30'], false))->assertUnprocessable()->assertJsonValidationErrors('calendar_date');
        }
    }

    public function test_contacts_are_batch_loaded_for_the_full_page(): void
    {
        for ($index = 0; $index < 15; $index++) {
            $appointment = Appointment::factory()->create();
            $this->alert($this->admin, (new NewCustomerBookingNotification($appointment))->toDatabase($this->admin));
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($this->admin)->get(route('notifications.index', absolute: false))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(3, count(array_filter($queries, fn ($query) => str_contains($query['query'], 'from `users`'))));
    }

    private function alert(User $user, array $data = [], bool $read = false, mixed $createdAt = null): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NewCustomerBookingNotification::class,
            'data' => array_merge((new NewCustomerBookingNotification($this->appointment))->toDatabase($user), $data),
            'read_at' => $read ? now() : null,
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
