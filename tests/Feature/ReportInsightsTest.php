<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportInsightsTest extends TestCase
{
    use RefreshDatabase;

    // ── Authorization ──────────────────────────────────────────────────────

    public function test_guest_cannot_access_insights(): void
    {
        $this->get(route('admin.reports.insights'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_insights(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get(route('admin.reports.insights'))
            ->assertForbidden();
    }

    // ── Basic response structure ───────────────────────────────────────────

    public function test_admin_gets_json_with_all_keys(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.insights'))
            ->assertOk()
            ->assertJsonStructure([
                'period',
                'revenue',
                'appointments_status',
                'sentiment',
                'top_services',
                'rfm_segments',
            ]);

        $this->assertSame('30', $response->json('period'));
    }

    // ── Period switching ───────────────────────────────────────────────────

    #[DataProvider('periodProvider')]
    public function test_valid_periods_are_accepted(string $period): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.reports.insights', ['period' => $period]))
            ->assertOk()
            ->assertJsonPath('period', $period);
    }

    public static function periodProvider(): array
    {
        return [
            ['7'],
            ['30'],
            ['90'],
            ['all'],
        ];
    }

    public function test_invalid_period_falls_back_to_30_days(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.reports.insights', ['period' => 'invalid']))
            ->assertOk()
            ->assertJsonPath('period', '30');
    }

    // ── Data is scoped correctly ───────────────────────────────────────────

    public function test_revenue_data_includes_paid_transactions_within_period(): void
    {
        $admin    = User::factory()->admin()->create();
        $service  = Service::factory()->create();
        $customer = CustomerProfile::factory()->for(User::factory()->customer(), 'user')->create();

        // Paid transaction within last 7 days
        Transaction::factory()->for($customer, 'customerProfile')->for($service)->create([
            'payment_status' => Transaction::PAYMENT_PAID,
            'amount'         => 500.00,
            'paid_at'        => now()->subDays(3),
        ]);

        // Paid transaction outside last 7 days
        Transaction::factory()->for($customer, 'customerProfile')->for($service)->create([
            'payment_status' => Transaction::PAYMENT_PAID,
            'amount'         => 999.00,
            'paid_at'        => now()->subDays(10),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.insights', ['period' => '7']))
            ->assertOk();

        $total = collect($response->json('revenue'))->sum('total');
        $this->assertEquals(500.00, $total);
    }

    public function test_all_period_includes_all_data(): void
    {
        $admin    = User::factory()->admin()->create();
        $service  = Service::factory()->create();
        $customer = CustomerProfile::factory()->for(User::factory()->customer(), 'user')->create();

        Transaction::factory()->for($customer, 'customerProfile')->for($service)->create([
            'payment_status' => Transaction::PAYMENT_PAID,
            'amount'         => 200.00,
            'paid_at'        => now()->subDays(120),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.insights', ['period' => 'all']))
            ->assertOk();

        $total = collect($response->json('revenue'))->sum('total');
        $this->assertEquals(200.00, $total);
    }

    public function test_appointments_status_groups_correctly(): void
    {
        $admin    = User::factory()->admin()->create();
        $service  = Service::factory()->create();
        $staff    = StaffProfile::factory()->for(User::factory()->staff(), 'user')->create();
        $customer = CustomerProfile::factory()->for(User::factory()->customer(), 'user')->create();

        Appointment::factory()->for($customer, 'customerProfile')->for($service)->for($staff, 'staffProfile')->create([
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
        Appointment::factory()->for($customer, 'customerProfile')->for($service)->for($staff, 'staffProfile')->create([
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.insights', ['period' => 'all']))
            ->assertOk();

        $statuses = collect($response->json('appointments_status'))->pluck('status')->all();
        $this->assertContains(Appointment::STATUS_CONFIRMED, $statuses);
        $this->assertContains(Appointment::STATUS_COMPLETED, $statuses);
    }

    public function test_sentiment_breakdown_groups_correctly(): void
    {
        $admin    = User::factory()->admin()->create();
        $service  = Service::factory()->create();
        $customer = CustomerProfile::factory()->for(User::factory()->customer(), 'user')->create();

        Feedback::factory()->for($customer, 'customerProfile')->for($service)->create([
            'sentiment_label' => 'positive',
            'rating'          => 5,
        ]);
        Feedback::factory()->for($customer, 'customerProfile')->for($service)->create([
            'sentiment_label' => 'negative',
            'rating'          => 1,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.insights', ['period' => 'all']))
            ->assertOk();

        $labels = collect($response->json('sentiment'))->pluck('label')->all();
        $this->assertContains('positive', $labels);
        $this->assertContains('negative', $labels);
    }
}
