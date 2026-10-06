<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentsLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CustomerProfile $customer;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->customer = CustomerProfile::factory()->create();
        $this->service = Service::factory()->create();
    }

    public function test_admin_and_super_admin_see_real_payment_fields_and_existing_actions(): void
    {
        $transaction = $this->payment(['amount' => 1234.50, 'payment_method' => Transaction::METHOD_GCASH]);

        foreach (['admin', 'super_admin'] as $role) {
            $user = $role === 'admin' ? $this->admin : User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('admin.transactions.index', absolute: false))
                ->assertOk()
                ->assertSee('data-payments-workspace', false)
                ->assertSee('Paid total')
                ->assertSee('Unpaid total')
                ->assertDontSee('Open balance')
                ->assertDontSee('Manual payment flow')
                ->assertSee($this->customer->user->email)
                ->assertSee('Sep 30, 2026')
                ->assertSee('1:00 PM')
                ->assertSee('₱1,234.50')
                ->assertSee('GCash')
                ->assertSee(route('admin.transactions.show', $transaction), false)
                ->assertSee(route('admin.transactions.edit', $transaction), false)
                ->assertSee(route('admin.transactions.create'), false)
                ->assertSee(route('admin.transactions.export'), false)
                ->assertSee('Scrollable payment records')
                ->assertSee('data-filter-toggle', false)
                ->assertSee('All dates');

            $this->assertSame(2, substr_count($response->getContent(), 'data-metric-card'));
            $this->actingAs($user)->get(route('admin.transactions.export', absolute: false))->assertOk()->assertDownload();
        }
    }

    public function test_email_search_status_and_visit_month_share_filtered_summary_and_export(): void
    {
        $this->customer->user->update(['email' => 'payments-match@example.com']);
        $paid = $this->payment(['amount' => 1500]);
        $this->payment(['amount' => 200, 'payment_status' => Transaction::PAYMENT_UNPAID]);
        $this->payment(['amount' => 999], '2026-10-01 13:00:00');
        $otherCustomer = CustomerProfile::factory()->create();
        $this->payment(['amount' => 888, 'customer_profile_id' => $otherCustomer->id]);
        $filters = ['q' => 'payments-match@', 'payment_status' => 'paid', 'visit_month' => '2026-09'];

        $this->actingAs($this->admin)->get(route('admin.transactions.index', $filters, false))
            ->assertOk()
            ->assertViewHas('transactions', fn ($rows) => $rows->getCollection()->modelKeys() === [$paid->id])
            ->assertViewHas('summary', fn ($summary) => (float) $summary['paid'] === 1500.0 && (float) $summary['unpaid'] === 0.0)
            ->assertSee('data-active-filters="3"', false)
            ->assertSee('Clear filters');

        $rows = $this->exportRows($filters);
        $this->assertCount(2, $rows);
        $this->assertSame($paid->transaction_number, $rows[1][0]);
        $this->assertSame('payments-match@example.com', $rows[1][2]);
        $this->assertSame('2026-09-30 13:00:00', $rows[1][3]);
    }

    public function test_visit_month_uses_inclusive_manila_start_and_exclusive_next_month(): void
    {
        $this->payment([], '2026-08-31 23:59:59');
        $first = $this->payment([], '2026-09-01 00:00:00');
        $last = $this->payment([], '2026-09-30 23:59:59');
        $this->payment([], '2026-10-01 00:00:00');
        $unlinked = $this->payment([], null);
        $filters = ['visit_month' => '2026-09', 'sort' => 'visit_date', 'direction' => 'asc'];

        $this->actingAs($this->admin)->get(route('admin.transactions.index', $filters, false))
            ->assertOk()
            ->assertViewHas('transactions', fn ($rows) => $rows->getCollection()->modelKeys() === [$first->id, $last->id])
            ->assertDontSee($unlinked->transaction_number);

        $this->assertSame([$first->transaction_number, $last->transaction_number], array_column(array_slice($this->exportRows($filters), 1), 0));
        $this->actingAs($this->admin)->get(route('admin.transactions.index', absolute: false))
            ->assertOk()->assertSee($unlinked->transaction_number)->assertSee('No linked visit');
    }

    public function test_totals_exclude_partial_refunded_and_void_and_cover_every_filtered_page(): void
    {
        $first = $this->payment(['amount' => 10.25]);
        Transaction::factory()->count(19)->create([
            'customer_profile_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'appointment_id' => $first->appointment_id,
            'recorded_by' => $this->admin->id,
            'amount' => 10.25,
        ]);
        $this->payment(['amount' => 20, 'payment_status' => Transaction::PAYMENT_UNPAID]);
        foreach ([Transaction::PAYMENT_PARTIAL, Transaction::PAYMENT_REFUNDED, Transaction::PAYMENT_VOID] as $status) {
            $this->payment(['amount' => 999, 'payment_status' => $status]);
        }
        $this->payment(['amount' => 5000], '2026-10-01 13:00:00');

        foreach ([1, 2] as $page) {
            $this->actingAs($this->admin)->get(route('admin.transactions.index', ['visit_month' => '2026-09', 'page' => $page], false))
                ->assertOk()
                ->assertViewHas('summary', fn ($summary) => (float) $summary['paid'] === 205.0 && (float) $summary['unpaid'] === 20.0);
        }

        $this->actingAs($this->admin)->get(route('admin.transactions.index', ['payment_status' => 'unpaid'], false))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary) => (float) $summary['paid'] === 0.0 && (float) $summary['unpaid'] === 20.0);
    }

    public function test_pagination_is_fixed_and_keeps_filters_and_sorting(): void
    {
        for ($index = 0; $index < 16; $index++) {
            $this->payment();
        }
        $filters = ['q' => $this->customer->user->email, 'visit_month' => '2026-09', 'payment_status' => 'paid', 'sort' => 'amount', 'direction' => 'asc', 'per_page' => 100];

        $this->actingAs($this->admin)->get(route('admin.transactions.index', $filters, false))
            ->assertOk()
            ->assertViewHas('transactions', fn ($rows) => $rows->perPage() === 15 && $rows->count() === 15 && $rows->total() === 16)
            ->assertSee('visit_month=2026-09', false)
            ->assertSee('payment_status=paid', false)
            ->assertSee('sort=amount', false)
            ->assertSee('direction=asc', false)
            ->assertDontSee('Rows per page');
    }

    public function test_search_keeps_number_name_and_service_support_and_sorting_is_stable(): void
    {
        $older = $this->payment(['amount' => 25, 'created_at' => '2026-09-30 12:00:00']);
        $newer = $this->payment(['amount' => 25, 'created_at' => '2026-09-30 12:00:00']);

        foreach ([$older->transaction_number, $this->customer->user->name, $this->service->name] as $search) {
            $this->actingAs($this->admin)->get(route('admin.transactions.index', ['q' => $search], false))
                ->assertOk()->assertSee($older->transaction_number);
        }
        $filters = ['sort' => 'amount', 'direction' => 'asc'];
        $this->actingAs($this->admin)->get(route('admin.transactions.index', $filters, false))
            ->assertOk()
            ->assertViewHas('transactions', fn ($rows) => $rows->getCollection()->modelKeys() === [$newer->id, $older->id]);
        $this->assertSame([$newer->transaction_number, $older->transaction_number], array_column(array_slice($this->exportRows($filters), 1), 0));
    }

    public function test_empty_and_filtered_empty_states_and_header_only_csv(): void
    {
        $this->actingAs($this->admin)->get(route('admin.transactions.index', absolute: false))
            ->assertOk()->assertSee('No transactions yet')->assertDontSee('Clear filters');
        $rows = $this->exportRows();
        $this->assertSame([['ID', 'Customer', 'Email', 'Visit date', 'Amount', 'Payment method', 'Status']], $rows);

        $this->payment();
        $this->actingAs($this->admin)->get(route('admin.transactions.index', ['q' => 'no-match-payment'], false))
            ->assertOk()->assertSee('No transactions found')->assertSee('Clear filters')
            ->assertViewHas('summary', fn ($summary) => (float) $summary['paid'] === 0.0 && (float) $summary['unpaid'] === 0.0)
            ->assertDontSee('data-pagination', false);
    }

    public function test_all_statuses_methods_and_escaped_long_customer_content_are_visible(): void
    {
        $name = '<script>alert("payment")</script>'.str_repeat(' Long customer', 15);
        $this->customer->user->update(['name' => $name, 'email' => str_repeat('customer', 15).'@example.com']);
        foreach (Transaction::PAYMENT_STATUSES as $index => $status) {
            $this->payment([
                'payment_status' => $status,
                'payment_method' => Transaction::PAYMENT_METHODS[$index] ?? null,
            ], null);
        }
        $response = $this->actingAs($this->admin)->get(route('admin.transactions.index', absolute: false))
            ->assertOk()->assertSee($name)->assertDontSee($name, false)->assertSee('Not set');
        foreach (Transaction::PAYMENT_METHODS as $method) {
            $response->assertSee($method);
        }
        foreach (Transaction::PAYMENT_STATUSES as $status) {
            $response->assertSee('payments-status--'.$status, false);
        }
    }

    public function test_export_streams_across_batches_and_neutralizes_formula_cells(): void
    {
        $this->customer->user->update(['name' => ' =SUM(1,1)']);
        $transactions = Transaction::factory()->count(501)->create([
            'customer_profile_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'appointment_id' => null,
            'recorded_by' => $this->admin->id,
            'created_at' => '2026-09-30 12:00:00',
        ]);
        $rows = $this->exportRows(['sort' => 'created', 'direction' => 'desc', 'page' => 2]);
        $this->assertCount(502, $rows);
        $this->assertSame($transactions->last()->transaction_number, $rows[1][0]);
        $this->assertSame($transactions->first()->transaction_number, $rows[501][0]);
        $this->assertSame("' =SUM(1,1)", $rows[1][1]);
        $this->assertSame('', $rows[1][3]);

        foreach (['+cmd', '-cmd', '@cmd'] as $name) {
            $this->customer->user->update(['name' => $name]);
            $this->assertSame("'".$name, $this->exportRows(['q' => $transactions->first()->transaction_number])[1][1]);
        }
    }

    public function test_list_and_export_validate_filters_and_deny_other_roles(): void
    {
        foreach (['admin.transactions.index', 'admin.transactions.export'] as $route) {
            $this->actingAs($this->admin)->getJson(route($route, ['q' => ['bad'], 'payment_status' => 'invalid', 'visit_month' => '2026-13', 'direction' => 'invalid'], false))
                ->assertUnprocessable()->assertJsonValidationErrors(['q', 'payment_status', 'visit_month', 'direction']);
            foreach (['customer', 'staff', 'receptionist'] as $role) {
                $this->actingAs(User::factory()->create(['role' => $role]))->get(route($route, absolute: false))->assertForbidden();
            }
        }
    }

    private function payment(array $attributes = [], ?string $visit = '2026-09-30 13:00:00'): Transaction
    {
        $appointment = $visit === null ? null : Appointment::factory()
            ->for($this->customer)->for($this->service)
            ->create([
                'scheduled_start_at' => CarbonImmutable::parse($visit, 'Asia/Manila'),
                'scheduled_end_at' => CarbonImmutable::parse($visit, 'Asia/Manila')->addHour(),
            ]);

        return Transaction::factory()->create(array_merge([
            'customer_profile_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'appointment_id' => $appointment?->id,
            'recorded_by' => $this->admin->id,
            'amount' => 100,
        ], $attributes));
    }

    private function exportRows(array $filters = []): array
    {
        $csv = $this->actingAs($this->admin)->get(route('admin.transactions.export', $filters, false))
            ->assertOk()->assertDownload()->streamedContent();

        return array_map(str_getcsv(...), explode("\n", trim($csv)));
    }
}
