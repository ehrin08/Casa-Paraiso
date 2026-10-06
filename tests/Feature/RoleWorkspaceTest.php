<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_module_routes_are_available_only_to_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        foreach ([
            '/admin/dashboard',
            '/admin/appointments',
            '/admin/customers',
            '/admin/staff',
            '/admin/services',
            '/admin/transactions',
            '/admin/promotions',
            '/admin/feedback',
            '/admin/reports',
            '/admin/settings',
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
            $this->actingAs($staff)->get($path)->assertForbidden();
            $this->actingAs($customer)->get($path)->assertForbidden();
        }
    }

    public function test_staff_module_routes_are_available_only_to_staff(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        foreach ([
            '/staff/dashboard',
            '/staff/appointments',
            '/staff/customers',
            '/staff/transactions',
            '/staff/feedback',
        ] as $path) {
            $this->actingAs($staff)->get($path)->assertOk();
            $this->actingAs($admin)->get($path)->assertForbidden();
            $this->actingAs($customer)->get($path)->assertForbidden();
        }
    }

    public function test_summary_metrics_use_the_compact_mobile_grid_contract(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();

        foreach ([
            ['/admin/dashboard', 1],
            ['/admin/transactions', 1],
            ['/admin/promotions', 1],
            ['/admin/feedback', 1],
            ['/admin/reports', 1],
        ] as [$path, $expectedGridCount]) {
            $content = $this->actingAs($admin)->get($path)->assertOk()->getContent();

            $this->assertSame($expectedGridCount, substr_count($content, 'data-metric-grid'));
            $this->assertStringContainsString('data-metric-card', $content);
            $this->assertStringContainsString('data-metric-meta', $content);
            if ($path === '/admin/transactions') {
                $this->assertSame(2, substr_count($content, 'data-metric-card'));
                $this->assertStringContainsString('Unpaid total', $content);
            } elseif ($path === '/admin/promotions') {
                $this->assertSame(4, substr_count($content, 'data-metric-card'));
                $this->assertStringContainsString('Filtered reward totals', $content);
            } else {
                $this->assertStringContainsString('hidden text-sm font-semibold leading-5 text-casa-muted sm:block', $content);
            }
        }

        $staffContent = $this->actingAs($staff)
            ->get('/staff/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($staffContent, 'data-metric-grid'));
        $this->assertStringContainsString('data-metric-card', $staffContent);
        $this->assertStringContainsString('data-metric-meta', $staffContent);
    }

    public function test_customer_module_routes_are_available_only_to_customers(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        foreach ([
            '/customer/appointments',
            '/customer/appointments/create',
            '/customer/feedback',
        ] as $path) {
            $this->actingAs($customer)->get($path)->assertOk();
            $this->actingAs($admin)->get($path)->assertForbidden();
            $this->actingAs($staff)->get($path)->assertForbidden();
        }

        $this->actingAs($customer)
            ->get('/customer/profile')
            ->assertRedirect(route('profile.edit', absolute: false));
    }

    public function test_customer_workspace_uses_sidebar_navigation(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get('/customer/appointments')
            ->assertOk()
            ->assertSee('data-page-loading', false)
            ->assertSee('data-turbo-track="reload"', false)
            ->assertSee('data-panel-host data-turbo="false"', false)
            ->assertDontSee('data-prefetch', false)
            ->assertSee('data-workspace-role="customer"', false)
            ->assertSee('data-role-navigation="customer"', false)
            ->assertSee('data-desktop-sidebar', false)
            ->assertSee('data-turbo-preload', false)
            ->assertSee('data-mobile-customer-navigation', false)
            ->assertSee('Customer lounge')
            ->assertSeeInOrder(['My appointments', 'My feedback', 'My profile'])
            ->assertSee(route('customer.appointments.index'), false)
            ->assertSee(route('customer.appointments.create'), false)
            ->assertSee(route('customer.feedback.index'), false)
            ->assertSee(route('profile.edit'), false)
            ->assertSee(route('logout'), false);

        $html = $this->actingAs($customer)->get('/customer/appointments')->getContent();
        $this->assertSame(2, substr_count($html, 'data-turbo-preload'));
    }

    public function test_admin_navigation_links_directly_to_each_manage_and_review_page(): void
    {
        $admin = User::factory()->admin()->create();

        $therapistsPage = $this->actingAs($admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSeeInOrder(['Daily work', 'Manage', 'Review', 'Account'])
            ->assertSee('href="'.route('admin.staff.index').'" data-sidebar-label="Therapists"', false)
            ->assertSee('href="'.route('admin.services.index').'" data-sidebar-label="Services"', false)
            ->assertSee('href="'.route('admin.promotions.index').'" data-sidebar-label="Customer rewards"', false)
            ->assertSee('href="'.route('admin.feedback.index').'" data-sidebar-label="Feedback"', false)
            ->assertSee('href="'.route('admin.reports.index').'" data-sidebar-label="Reports"', false)
            ->assertDontSee('data-sidebar-label="User accounts"', false);

        $this->assertMatchesRegularExpression('/<a[^>]*data-sidebar-label="Therapists"[^>]*aria-current="page"/', $therapistsPage->getContent());
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*data-sidebar-label="Services"[^>]*aria-current="page"/', $therapistsPage->getContent());

        $servicesPage = $this->actingAs($admin)
            ->get(route('admin.services.index'))
            ->assertOk();

        $this->assertMatchesRegularExpression('/<a[^>]*data-sidebar-label="Services"[^>]*aria-current="page"/', $servicesPage->getContent());
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*data-sidebar-label="Therapists"[^>]*aria-current="page"/', $servicesPage->getContent());
    }

    public function test_each_role_gets_plain_language_navigation_for_its_work(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($receptionist)
            ->get(route('reception.dashboard'))
            ->assertOk()
            ->assertSee('data-sidebar-label="Staff attendance"', false)
            ->assertSee('data-sidebar-label="Appointments"', false)
            ->assertDontSee('data-sidebar-label="Reports"', false);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('data-sidebar-label="My appointments"', false)
            ->assertSee('data-sidebar-label="My attendance"', false)
            ->assertSee('data-sidebar-label="My commissions"', false);

        $this->actingAs($customer)
            ->get(route('customer.appointments.index'))
            ->assertOk()
            ->assertSee('data-sidebar-label="My appointments"', false)
            ->assertSee('data-sidebar-label="My feedback"', false)
            ->assertSee('data-mobile-customer-navigation', false)
            ->assertSee('<span>My profile</span>', false)
            ->assertDontSee('data-sidebar-label="Payments"', false);
    }

    public function test_authenticated_web_responses_include_application_database_and_query_timing(): void
    {
        $customer = User::factory()->customer()->create();

        $timing = $this->actingAs($customer)
            ->get('/customer/appointments')
            ->assertOk()
            ->headers->get('Server-Timing');

        $this->assertIsString($timing);
        $this->assertStringContainsString('app;dur=', $timing);
        $this->assertStringContainsString('db;dur=', $timing);
        $this->assertStringContainsString('queries;desc=', $timing);
    }

    public function test_turbo_exclusions_are_explicit_for_panels_logout_and_exports(): void
    {
        $admin = User::factory()->admin()->create();
        $customerProfile = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create(['status' => Appointment::STATUS_CONFIRMED]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('data-panel-link data-turbo="false"', false)
            ->assertSee('action="'.route('logout').'" class="mt-auto pt-6" data-turbo="false"', false);

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('href="'.route('admin.reports.export').'" class="casa-button-primary" data-turbo="false"', false);
    }

    public function test_authenticated_landing_page_links_directly_to_role_home(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        foreach ([
            [$admin, route('admin.dashboard')],
            [$staff, route('staff.dashboard')],
            [$customer, route('customer.appointments.index')],
        ] as [$user, $homeUrl]) {
            $this->actingAs($user)
                ->get('/')
                ->assertOk()
                ->assertSee('href="'.$homeUrl.'"', false)
                ->assertDontSee('href="'.url('/dashboard').'"', false);
        }
    }

    public function test_admin_dashboard_shows_operational_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customerProfile = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'Hilot Massage']);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'appointment_number' => 'APT-UPCOMING',
                'status' => Appointment::STATUS_CONFIRMED,
                'requested_start_at' => now()->addDay()->setTime(14, 0),
                'scheduled_start_at' => now()->addDay()->setTime(14, 0),
                'scheduled_end_at' => now()->addDay()->setTime(15, 0),
            ]);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->for(StaffProfile::factory()->for($staff), 'staffProfile')
            ->create([
                'appointment_number' => 'APT-TODAY',
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->setTime(10, 0),
                'scheduled_end_at' => now()->setTime(11, 0),
            ]);

        Transaction::factory()
            ->for($customerProfile)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'amount' => 1200,
                'payment_status' => Transaction::PAYMENT_PAID,
                'paid_at' => now(),
            ]);

        Feedback::factory()
            ->for($customerProfile)
            ->for($service)
            ->create(['submitted_at' => now()]);

        PromotionSuggestion::factory()
            ->for($customerProfile)
            ->create(['status' => PromotionSuggestion::STATUS_SUGGESTED]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('APT-UPCOMING')
            ->assertSee('Hilot Massage')
            ->assertSee('₱1,200.00')
            ->assertSee('1 customer reward available');
    }

    public function test_admin_dashboard_prioritizes_confirmed_visits_that_are_today_or_overdue(): void
    {
        Carbon::setTestNow('2026-08-20 15:00:00');

        try {
            $admin = User::factory()->admin()->create();
            $customerProfile = CustomerProfile::factory()->create();
            $service = Service::factory()->create();

            Appointment::factory()->for($customerProfile)->for($service)->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->subHour(),
                'scheduled_end_at' => now(),
            ]);
            Appointment::factory()->for($customerProfile)->for($service)->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->addHour(),
                'scheduled_end_at' => now()->addHours(2),
            ]);
            Appointment::factory()->for($customerProfile)->for($service)->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->addDay(),
                'scheduled_end_at' => now()->addDay()->addHour(),
            ]);

            $this->actingAs($admin)
                ->get('/admin/dashboard')
                ->assertOk()
                ->assertViewHas('summary', fn ($summary) => $summary['confirmedToday'] === 2 && $summary['overdueConfirmed'] === 1)
                ->assertSee('1 overdue confirmed visit')
                ->assertSee('Scheduled today');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function legacy_staff_dashboard_shows_assigned_pending_and_completed_counts(): void
    {
        $staff = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->for($staff)->create();
        $customerProfile = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'Signature Body Care']);

        $staffProfile->services()->attach($service);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->for($staffProfile, 'staffProfile')
            ->create([
                'appointment_number' => 'APT-STAFF',
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->setTime(9, 0),
                'scheduled_end_at' => now()->setTime(10, 0),
            ]);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'appointment_number' => 'APT-CANCELLED-STAFF',
                'status' => Appointment::STATUS_CANCELLED,
            ]);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->for($staffProfile, 'staffProfile')
            ->create([
                'appointment_number' => 'APT-DONE',
                'status' => Appointment::STATUS_COMPLETED,
                'scheduled_start_at' => now()->setTime(11, 0),
                'scheduled_end_at' => now()->setTime(12, 0),
                'completed_at' => now(),
            ]);

        $this->actingAs($staff)
            ->get('/staff/dashboard')
            ->assertOk()
            ->assertSee('APT-STAFF')
            ->assertSee('Signature Body Care')
            ->assertSeeInOrder(['Assigned today', '1', 'Confirmed appointments'])
            ->assertSeeInOrder(['Completed', '1', 'Services finished today']);
    }

    public function test_customer_appointment_lounge_shows_own_appointment_summary(): void
    {
        $customer = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customer)->create();
        $service = Service::factory()->create(['name' => 'Tropical Wellness Massage']);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'appointment_number' => 'APT-CUSTOMER-CANCELLED',
                'staff_profile_id' => null,
                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
                'status' => Appointment::STATUS_CANCELLED,
                'confirmed_at' => null,
                'cancelled_at' => now(),
                'requested_start_at' => now()->addDays(2)->setTime(14, 0),
            ]);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->confirmed()
            ->create([
                'appointment_number' => 'APT-CUSTOMER-CONFIRMED',
                'scheduled_start_at' => now()->addDay()->setTime(15, 0),
                'scheduled_end_at' => now()->addDay()->setTime(16, 0),
            ]);

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'appointment_number' => 'APT-CUSTOMER-COMPLETE',
                'status' => Appointment::STATUS_COMPLETED,
                'requested_start_at' => now()->subDay()->setTime(14, 0),
                'scheduled_start_at' => now()->subDay()->setTime(14, 0),
                'scheduled_end_at' => now()->subDay()->setTime(15, 0),
                'completed_at' => now()->subDay(),
            ]);

        $this->actingAs($customer)
            ->get('/customer/appointments')
            ->assertOk()
            ->assertSee('data-customer-appointment-calendar', false)
            ->assertSeeInOrder(['Upcoming', '1'])
            ->assertSeeInOrder(['Cancelled', '1'])
            ->assertSeeInOrder(['Completed', '1']);

        $response = $this->actingAs($customer)->getJson(route('customer.appointments.calendar', [
            'month' => now()->format('Y-m'),
        ], false));

        $response->assertOk();
        $numbers = collect($response->json('events'))->pluck('appointment_number');
        $this->assertTrue($numbers->contains('APT-CUSTOMER-CANCELLED'));
        $this->assertTrue($numbers->contains('APT-CUSTOMER-CONFIRMED'));
        $this->assertTrue($numbers->contains('APT-CUSTOMER-COMPLETE'));
        $this->assertTrue(collect($response->json('events'))->pluck('service_name')->contains('Tropical Wellness Massage'));
    }
}
