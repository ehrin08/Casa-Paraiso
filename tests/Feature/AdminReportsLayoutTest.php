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
use App\Services\ReportDashboard;
use App\Services\ReportDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    private StaffProfile $staff;

    private CustomerProfile $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 14:00:00', 'Asia/Manila'));
        $this->admin = User::factory()->admin()->create();
        $this->service = Service::factory()->create(['name' => 'Report Massage']);
        $this->staff = StaffProfile::factory()->create();
        $this->guest = $this->customer('Report Guest');
    }

    public function test_admin_and_super_admin_get_server_rendered_dashboard_and_real_empty_states(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $this->admin->update(['role' => $role]);
            $response = $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk()
                ->assertSee('data-page-heading', false)->assertSee('data-reports-workspace', false)
                ->assertSee('Total revenue')->assertSee('Completed appointments')->assertSee('Unique customers')
                ->assertSee('Revenue by week')->assertSee('Appointment status')->assertSee('Feedback sentiment')
                ->assertSee('Top services by revenue')->assertSee('Customers by RFM group')
                ->assertSee('No prior activity')->assertSee('No paid transactions in this period.')
                ->assertSee('No appointments in this period.')->assertSee('No feedback in this period.')
                ->assertSee('No report rows')->assertSee('data-turbo="false"', false)
                ->assertDontSee('cdn.jsdelivr.net')->assertDontSee('casaInsights', false)
                ->assertViewHas('range', fn ($range) => $range->from->toDateString() === '2026-10-01' && $range->to->toDateString() === '2026-10-31')
                ->assertViewHas('dashboard', fn ($data) => $data['metrics']['revenue']['value'] === 0.0 && count($data['revenue']['buckets']) === 5);
            $this->assertSame(1, $response->viewData('dashboard')['rfm']['total']);
        }
    }

    public function test_month_dates_use_business_fields_and_count_distinct_completed_guest_customers(): void
    {
        foreach (['2026-09-30 23:59:59', '2026-10-01 00:00:00', '2026-10-31 23:59:59', '2026-11-01 00:00:00'] as $date) {
            $this->visit($date, Appointment::STATUS_COMPLETED);
            $this->payment($date, 100);
        }
        $fallback = $this->visit('2026-10-15 14:00:00', Appointment::STATUS_COMPLETED);
        $fallback->update(['scheduled_start_at' => null]);
        $this->visit('2026-10-12 14:00:00', Appointment::STATUS_CANCELLED);
        $this->visit('2026-10-13 14:00:00', Appointment::STATUS_NO_SHOW);
        $this->visit('2026-10-14 14:00:00', Appointment::STATUS_CONFIRMED);
        foreach ([Transaction::PAYMENT_UNPAID, Transaction::PAYMENT_PARTIAL, Transaction::PAYMENT_REFUNDED, Transaction::PAYMENT_VOID] as $status) {
            $this->payment('2026-10-05 14:00:00', 900, $status);
        }
        $data = $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk()->viewData('dashboard');
        $this->assertSame(200.0, $data['metrics']['revenue']['value']);
        $this->assertSame(9, $data['metrics']['completed']['value']);
        $this->assertSame(1, $data['metrics']['customers']['value']);
        $this->assertSame(100.0, $data['metrics']['revenue']['change']);
        $this->assertSame(12, $data['appointments']['total']);
        $this->assertSame([1, 9, 1, 1], array_column($data['appointments']['rows'], 'count'));
        $this->assertSame([100.0, 0.0, 0.0, 0.0, 100.0], array_column($data['revenue']['buckets'], 'total'));
    }

    public function test_presets_custom_precedence_one_sided_dates_and_comparisons(): void
    {
        foreach ([
            ['last_month', '2026-09-01', '2026-09-30', '2026-08-01', '2026-08-31'],
            ['last_30_days', '2026-09-12', '2026-10-11', '2026-08-13', '2026-09-11'],
        ] as [$key, $from, $to, $priorFrom, $priorTo]) {
            $range = ReportDateRange::fromFilters(['range' => $key]);
            $this->assertSame($from, $range->from->toDateString());
            $this->assertSame($to, $range->to->toDateString());
            $this->assertSame($priorFrom, $range->previous()->from->toDateString());
            $this->assertSame($priorTo, $range->previous()->to->toDateString());
        }
        $range = ReportDateRange::fromFilters(['range' => 'all', 'date_from' => '2026-10-03', 'date_to' => '2026-10-05']);
        $this->assertSame('custom', $range->key);
        $this->assertSame('2026-09-30', $range->previous()->from->toDateString());
        $this->assertSame('2026-10-02', $range->previous()->to->toDateString());
        $this->assertSame('day', app(ReportDashboard::class)->forRange($range)['revenue']['unit']);
        $this->actingAs($this->admin)->get(route('admin.reports.index', ['date_to' => '2026-09-30']))->assertOk()
            ->assertViewHas('range', fn ($range) => $range->from === null && $range->previous() === null);
        $this->get(route('admin.reports.index', ['range' => 'all']))->assertOk()->assertDontSee('vs Sep')
            ->assertViewHas('dashboard', fn ($data) => ! $data['metrics']['revenue']['has_previous']);
        $this->assertNull(ReportDateRange::fromFilters(['date_from' => '2026-01-01'])->previous());
    }

    public function test_service_rankings_use_revenue_and_include_deleted_services(): void
    {
        $other = Service::factory()->create(['name' => 'Historical Treatment']);
        $this->payment('2026-10-05 15:00:00', 2000, service: $other);
        $other->delete();
        foreach (range(1, 6) as $i) {
            $this->payment('2026-10-06 15:00:00', 100);
        }
        $data = $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk()->viewData('dashboard');
        $this->assertSame(['Historical Treatment', 'Report Massage'], $data['services']->pluck('name')->all());
        $this->assertSame([2000.0, 600.0], $data['services']->pluck('total')->map(fn ($value) => (float) $value)->all());
    }

    public function test_feedback_uses_submission_date_and_includes_unclassified_labels(): void
    {
        foreach ([['positive', '2026-10-01'], ['neutral', '2026-10-02'], ['negative', '2026-10-03'], ['unrated', '2026-10-04'], ['positive', '2026-09-30']] as [$label, $date]) {
            Feedback::factory()->create(['customer_profile_id' => $this->guest->id, 'service_id' => $this->service->id, 'appointment_id' => $this->visit($date.' 14:00:00')->id, 'sentiment_label' => $label, 'submitted_at' => $date.' 23:59:59']);
        }
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk()->assertSee('Unclassified');
        $data = $response->viewData('dashboard')['sentiment'];
        $this->assertSame(4, $data['total']);
        $this->assertSame([1, 1, 1, 1], array_column($data['rows'], 'count'));
        $this->assertSame([25.0, 25.0, 25.0, 25.0], array_column($data['rows'], 'percentage'));
    }

    public function test_category_focus_selects_matching_records_and_tabs_preserve_range(): void
    {
        $this->actingAs($this->admin);
        foreach (['appointments', 'transactions', 'customers', 'promotions', 'feedback'] as $category) {
            $this->get(route('admin.reports.index', ['category' => $category, 'type' => 'appointments', 'range' => 'last_month']))->assertOk()
                ->assertViewHas('type', $category)->assertViewHas('category', $category)
                ->assertSee('href="'.e(route('admin.reports.index', ['range' => 'last_month', 'category' => 'feedback', 'type' => 'feedback'])).'"', false);
        }
        $this->get(route('admin.reports.index', ['category' => 'transactions']))->assertOk()
            ->assertSee('Top services by revenue')->assertDontSee('id="reports-rfm-heading"', false)
            ->assertDontSee('data-report-metric="customers"', false);
        $this->get(route('admin.reports.index', ['type' => 'transactions']))->assertOk()
            ->assertSee('id="reports-rfm-heading"', false)->assertViewHas('category', 'all');
    }

    public function test_record_filters_do_not_change_dashboard_and_export_includes_all_matching_pages(): void
    {
        $this->guest->update(['guest_name' => '=Report Guest']);
        foreach (range(1, 17) as $i) {
            $this->payment('2026-10-05 14:00:00', $i)->update(['transaction_number' => sprintf('REPORT-%02d', $i)]);
        }
        $this->payment('2026-09-30 23:59:59', 999)->update(['transaction_number' => 'OUTSIDE-RANGE']);
        $params = ['type' => 'transactions', 'q' => 'Report Guest', 'sort' => 'amount', 'direction' => 'asc'];
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', $params))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 17 && $records->count() === 15)
            ->assertSee('REPORT-01')->assertDontSee('REPORT-17')->assertDontSee('OUTSIDE-RANGE');
        $this->assertSame(153.0, $response->viewData('dashboard')['metrics']['revenue']['value']);
        preg_match('/queries;desc="(\d+)"/', $response->headers->get('Server-Timing'), $timing);
        $this->assertLessThanOrEqual(25, (int) $timing[1], 'Reports queries must stay bounded as the record count grows.');
        $this->get(route('admin.reports.index', [...$params, 'page' => 2]))->assertOk()->assertSee('REPORT-17')
            ->assertViewHas('records', fn ($records) => $records->count() === 2);
        $this->get(route('admin.reports.index', [...$params, 'q' => 'No match']))->assertOk()
            ->assertViewHas('dashboard', fn ($data) => $data['metrics']['revenue']['value'] === 153.0);
        $csv = $this->get(route('admin.reports.export', $params))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertCount(18, $rows);
        $this->assertSame('REPORT-01', $rows[1][0]);
        $this->assertSame('REPORT-17', $rows[17][0]);
        $this->assertSame("'=Report Guest", $rows[1][1]);
        $this->assertStringNotContainsString('OUTSIDE-RANGE', $csv);
    }

    public function test_rfm_uses_entire_base_history_cutoff_precedence_and_period_averages_without_writes(): void
    {
        foreach (range(1, 4) as $i) {
            $this->payment('2026-09-10 14:00:00', 1500);
        }
        $new = $this->customer('New guest');
        $this->payment('2026-09-15 14:00:00', 500, customer: $new);
        $risk = $this->customer('At risk');
        foreach (range(1, 2) as $i) {
            $this->payment('2026-06-15 14:00:00', 100, customer: $risk);
        }
        $inactive = $this->customer('Inactive');
        $this->payment('2026-01-10 14:00:00', 100, customer: $inactive);
        $noHistory = $this->customer('No history');
        $futureVisit = $this->payment('2026-09-20 14:00:00', 100, customer: $noHistory);
        $futureVisit->appointment->update(['scheduled_start_at' => '2026-10-01 14:00:00']);
        $this->customer('Future profile')->forceFill(['created_at' => '2026-10-01 00:00:00'])->save();
        $this->payment('2026-10-05 14:00:00', 9000, customer: $new);
        $this->payment('2026-09-20 14:00:00', 5000, Transaction::PAYMENT_UNPAID, customer: $new);
        $uncompleted = $this->payment('2026-09-25 14:00:00', 5000, customer: $new);
        $uncompleted->appointment->update(['status' => Appointment::STATUS_CANCELLED]);
        $before = [CustomerProfile::count(), Transaction::count(), PromotionSuggestion::count()];
        $data = $this->actingAs($this->admin)->get(route('admin.reports.index', ['range' => 'last_month']))->assertOk()->viewData('dashboard');
        $groups = collect($data['rfm']['rows'])->keyBy('key');
        $this->assertSame(5, $data['rfm']['total']);
        $this->assertSame(1, $groups['high_value']['customers']);
        $this->assertSame(0, $groups['loyal']['customers']);
        $this->assertSame(1, $groups['new_customer']['customers']);
        $this->assertSame(1, $groups['at_risk']['customers']);
        $this->assertSame(1, $groups['inactive']['customers']);
        $this->assertSame(1, $groups['unassigned']['customers']);
        $this->assertSame(6000.0, $groups['high_value']['average']);
        $this->assertSame(5500.0, $groups['new_customer']['average']);
        $this->assertSame(100.0, $groups['unassigned']['average']);
        $this->assertSame(20.0, $groups['new_customer']['percentage']);
        $this->assertSame('2026-09-30', $data['cutoff']->toDateString());
        $this->assertSame($before, [CustomerProfile::count(), Transaction::count(), PromotionSuggestion::count()]);
    }

    public function test_long_history_uses_monthly_zero_filled_revenue_buckets(): void
    {
        $this->payment('2026-01-15 14:00:00', 100);
        $this->payment('2026-10-05 14:00:00', 200);
        $data = $this->actingAs($this->admin)->get(route('admin.reports.index', ['range' => 'all']))->assertOk()->viewData('dashboard');
        $this->assertSame('month', $data['revenue']['unit']);
        $this->assertCount(10, $data['revenue']['buckets']);
        $this->assertSame(0.0, $data['revenue']['buckets'][1]['total']);
        $this->assertSame(300.0, array_sum(array_column($data['revenue']['buckets'], 'total')));
    }

    public function test_invalid_filters_are_rejected_for_both_records_and_export(): void
    {
        $this->actingAs($this->admin);
        foreach (['index', 'export'] as $action) {
            foreach ([['category' => 'invalid'], ['range' => 'invalid'], ['date_from' => 'invalid'], ['date_from' => '2026-10-10', 'date_to' => '2026-10-01'], ['type' => 'invalid'], ['page' => 0]] as $invalid) {
                $this->getJson(route('admin.reports.'.$action, $invalid))->assertUnprocessable();
            }
        }
    }

    public function test_reports_and_exports_are_admin_only(): void
    {
        foreach (['index', 'export'] as $action) {
            $this->get(route('admin.reports.'.$action))->assertRedirect(route('login'));
        }
        foreach (['customer', 'staff', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['index', 'export'] as $action) {
                $this->get(route('admin.reports.'.$action))->assertForbidden();
            }
        }
    }

    private function customer(string $name): CustomerProfile
    {
        return CustomerProfile::factory()->create(['user_id' => null, 'guest_name' => $name, 'created_at' => '2026-01-01 00:00:00']);
    }

    private function visit(string $date, string $status = Appointment::STATUS_CONFIRMED, ?CustomerProfile $customer = null, ?Service $service = null): Appointment
    {
        return Appointment::factory()->create(['customer_profile_id' => ($customer ?? $this->guest)->id, 'service_id' => ($service ?? $this->service)->id, 'staff_profile_id' => $this->staff->id, 'created_by' => $this->admin->id, 'status' => $status, 'scheduled_start_at' => $date, 'requested_start_at' => $date, 'scheduled_end_at' => CarbonImmutable::parse($date)->addHour(), 'created_at' => '2026-01-01 00:00:00']);
    }

    private function payment(string $date, float $amount, string $status = Transaction::PAYMENT_PAID, ?CustomerProfile $customer = null, ?Service $service = null): Transaction
    {
        $visit = $this->visit($date, Appointment::STATUS_COMPLETED, $customer, $service);

        return Transaction::factory()->create(['appointment_id' => $visit->id, 'customer_profile_id' => ($customer ?? $this->guest)->id, 'service_id' => ($service ?? $this->service)->id, 'recorded_by' => $this->admin->id, 'paid_at' => $date, 'amount' => $amount, 'payment_status' => $status]);
    }
}
