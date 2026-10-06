<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCommissionsLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private StaffProfile $staff;

    private Appointment $appointment;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->staff = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'Signature massage']);
        $this->appointment = Appointment::factory()->for($customer)->for($service)->for($this->staff, 'staffProfile')->create([
            'status' => Appointment::STATUS_COMPLETED,
            'scheduled_start_at' => '2026-09-30 14:30:00',
            'scheduled_end_at' => '2026-09-30 15:30:00',
            'completed_at' => '2026-09-30 15:30:00',
        ]);
        $this->transaction = Transaction::factory()->for($customer)->for($service)->for($this->appointment)->create();
    }

    public function test_admin_and_super_admin_see_snapshot_amounts_and_shared_detail_panel(): void
    {
        $commission = $this->commission(['basis_amount' => 1234.50, 'commission_rate' => 0.225, 'commission_amount' => 277.76]);

        foreach ([$this->admin, User::factory()->create(['role' => 'super_admin'])] as $user) {
            $this->actingAs($user)->get(route('admin.commissions.index', absolute: false))
                ->assertOk()->assertSee('data-commissions-workspace', false)
                ->assertSee('Net total')->assertSee('Basis amount')->assertSee('22.5%')
                ->assertSee('₱1,234.50')->assertSee('₱277.76')->assertDontSee('Deductions')
                ->assertSee($this->staff->user->email)->assertSee('Sep 30, 2026')->assertSee('2:30 PM')
                ->assertSee('Signature massage')->assertSee('Mark as paid')
                ->assertSee('data-selected-commission="'.$commission->id.'"', false)
                ->assertSee(route('admin.commissions.export'), false)
                ->assertSee('Scrollable therapist commission records')->assertSee('data-filter-toggle', false);
            $this->actingAs($user)->get(route('admin.commissions.show', $commission, false))
                ->assertOk()->assertSee('Service breakdown')->assertSee('₱277.76')
                ->assertSee(route('admin.appointments.show', $this->appointment), false)
                ->assertSee(route('admin.transactions.show', $this->transaction), false);
            $this->actingAs($user)->get(route('admin.commissions.export', absolute: false))->assertOk()->assertDownload();
        }
    }

    public function test_totals_follow_every_filter_and_include_signed_adjustments_across_pages(): void
    {
        for ($index = 0; $index < 16; $index++) {
            $this->commission(['commission_amount' => 10.25]);
        }
        $this->commission(['commission_type' => 'adjustment', 'commission_amount' => -4.50]);
        $this->commission(['status' => 'paid', 'commission_amount' => 50]);
        $this->commission(['earned_at' => '2026-10-01 00:00:00', 'commission_amount' => 999]);
        $this->commission(['staff_profile_id' => StaffProfile::factory()->create()->id, 'commission_amount' => 888]);
        $filters = ['staff_profile_id' => $this->staff->id, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'];

        foreach ([1, 2] as $page) {
            $this->actingAs($this->admin)->get(route('admin.commissions.index', $filters + ['page' => $page], false))
                ->assertOk()->assertViewHas('totals', fn ($totals) => (float) $totals['pending'] === 159.50 && (float) $totals['paid'] === 50.0 && (float) $totals['net'] === 209.50);
        }
        $this->actingAs($this->admin)->get(route('admin.commissions.index', $filters + ['status' => 'pending'], false))
            ->assertOk()->assertViewHas('totals', fn ($totals) => (float) $totals['pending'] === 159.50 && (float) $totals['paid'] === 0.0)
            ->assertSee('data-active-filters="4"', false)->assertSee('Clear filters');
        $this->assertCount(18, $this->exportRows($filters + ['status' => 'pending']));
    }

    public function test_date_boundaries_and_tied_order_match_the_export(): void
    {
        $this->commission(['earned_at' => '2026-08-31 23:59:59']);
        $first = $this->commission(['earned_at' => '2026-09-01 00:00:00']);
        $last = $this->commission(['earned_at' => '2026-09-30 23:59:59']);
        $tied = $this->commission(['earned_at' => '2026-09-30 23:59:59']);
        $this->commission(['earned_at' => '2026-10-01 00:00:00']);
        $filters = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30'];

        $this->actingAs($this->admin)->get(route('admin.commissions.index', $filters, false))
            ->assertOk()->assertViewHas('commissions', fn ($rows) => $rows->getCollection()->modelKeys() === [$tied->id, $last->id, $first->id]);
        $this->assertSame(array_map(strval(...), [$tied->id, $last->id, $first->id]), array_column(array_slice($this->exportRows($filters), 1), 0));
    }

    public function test_selection_is_limited_to_the_current_filtered_page_and_pagination_resets_it(): void
    {
        $records = collect();
        for ($index = 0; $index < 16; $index++) {
            $records->push($this->commission());
        }
        $filters = ['staff_profile_id' => $this->staff->id, 'status' => 'pending', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'];
        $selected = $records[10];
        $response = $this->actingAs($this->admin)->get(route('admin.commissions.index', $filters + ['selected' => $selected->id, 'per_page' => 100], false))
            ->assertOk()->assertViewHas('selectedCommission', fn ($row) => $row->id === $selected->id)
            ->assertViewHas('commissions', fn ($rows) => $rows->perPage() === 15 && $rows->count() === 15 && $rows->total() === 16);
        parse_str((string) parse_url($response->viewData('commissions')->url(2), PHP_URL_QUERY), $nextPage);
        $this->assertSame(array_map(strval(...), $filters), array_intersect_key($nextPage, $filters));
        $this->assertArrayNotHasKey('selected', $nextPage);
        $this->assertArrayNotHasKey('per_page', $nextPage);

        foreach ([$records->first()->id, 999999, 'invalid', ['invalid']] as $selection) {
            $this->actingAs($this->admin)->get(route('admin.commissions.index', $filters + ['selected' => $selection], false))
                ->assertOk()->assertViewHas('selectedCommission', fn ($row) => $row->id === $records->last()->id);
        }
        $this->actingAs($this->admin)->get(route('admin.commissions.index', $filters + ['page' => 2, 'selected' => $selected->id], false))
            ->assertOk()->assertViewHas('selectedCommission', fn ($row) => $row->id === $records->first()->id);
        $this->actingAs($this->admin)->get(route('admin.commissions.index', ['status' => 'paid', 'selected' => $selected->id], false))
            ->assertOk()->assertViewHas('selectedCommission', null);
    }

    public function test_payout_from_list_preserves_filters_and_clamps_an_empty_last_page(): void
    {
        $record = $this->commission();
        for ($index = 0; $index < 15; $index++) {
            $this->commission();
        }
        $filters = ['staff_profile_id' => $this->staff->id, 'status' => 'pending', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'page' => 2];
        $response = $this->actingAs($this->admin)->patch(route('admin.commissions.pay', $record, false), [
            'paid_at' => today()->toDateString(), 'notes' => ' Settled externally. ',
            'return_to' => 'index', 'return_filters' => $filters,
        ])->assertSessionHas('status', 'commission-paid');
        $filters['page'] = 1;
        $response->assertRedirect(route('admin.commissions.index', $filters + ['selected' => $record->id], false));
        $this->assertSame('paid', $record->fresh()->status);
        $this->assertSame('Settled externally.', $record->fresh()->notes);
        $this->assertSame($this->admin->id, $record->fresh()->paid_by);
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertViewHas('selectedCommission', fn ($row) => $row->id !== $record->id);
    }

    public function test_standalone_payout_redirect_is_preserved_and_null_list_context_is_safe(): void
    {
        $record = $this->commission();
        $this->actingAs($this->admin)->patch(route('admin.commissions.pay', $record, false), ['paid_at' => today()->toDateString()])
            ->assertRedirect(route('admin.commissions.show', $record, false));
        $other = $this->commission(['commission_type' => 'adjustment', 'commission_amount' => -44]);
        $this->patch(route('admin.commissions.pay', $other, false), ['paid_at' => today()->toDateString(), 'return_to' => 'index', 'return_filters' => null])
            ->assertRedirect(route('admin.commissions.index', ['page' => 1, 'selected' => $other->id], false));
    }

    public function test_invalid_payout_retains_values_and_opens_the_panel_form(): void
    {
        $record = $this->commission();
        $index = route('admin.commissions.index', ['selected' => $record->id], false);
        $future = today()->addDay()->toDateString();
        $this->actingAs($this->admin)->from($index)->patch(route('admin.commissions.pay', $record, false), [
            'paid_at' => $future, 'notes' => 'Retain this note', 'return_to' => 'index',
        ])->assertRedirect($index)->assertSessionHasErrors('paid_at');
        $this->get($index)->assertOk()->assertSee('class="commissions-payout"  open', false)
            ->assertSee('value="'.$future.'"', false)->assertSee('Retain this note');
        $this->assertSame('pending', $record->fresh()->status);
    }

    public function test_return_context_validation_prevents_arbitrary_redirects_and_invalid_filters(): void
    {
        $record = $this->commission();
        $payloads = [
            ['return_to' => 'https://example.com'],
            ['return_to' => 'index', 'return_filters' => ['redirect' => 'https://example.com']],
            ['return_to' => 'index', 'return_filters' => ['status' => 'invalid', 'page' => -1]],
            ['return_to' => 'index', 'return_filters' => ['date_from' => '2026-09-30', 'date_to' => '2026-09-01']],
        ];
        foreach ($payloads as $payload) {
            $this->actingAs($this->admin)->patchJson(route('admin.commissions.pay', $record, false), $payload + ['paid_at' => today()->toDateString()])
                ->assertUnprocessable();
            $this->assertSame('pending', $record->fresh()->status);
        }
    }

    public function test_paid_zero_and_adjustment_records_show_truthful_states_and_source_links(): void
    {
        $paid = $this->commission(['status' => 'paid', 'paid_at' => '2026-09-30 23:59:59', 'paid_by' => $this->admin->id, 'notes' => '<script>payout</script>']);
        $adjustment = $this->commission(['commission_type' => 'adjustment', 'adjusts_commission_id' => $paid->id, 'commission_amount' => -44]);
        $zero = $this->commission(['commission_amount' => 0]);
        $this->staff->delete();

        $this->actingAs($this->admin)->get(route('admin.commissions.show', $paid, false))
            ->assertOk()->assertSee('This payout record is immutable.')->assertDontSee('Mark as paid')
            ->assertSee($this->admin->name)->assertSee('<script>payout</script>')->assertDontSee('<script>payout</script>', false)
            ->assertSee(route('admin.commissions.show', $adjustment), false);
        $this->get(route('admin.commissions.show', $adjustment, false))->assertOk()
            ->assertSee('₱-44')->assertSee('Adjusts commission')->assertSee(route('admin.commissions.show', $paid), false)->assertSee('Mark as paid');
        $this->get(route('admin.commissions.show', $zero, false))->assertOk()
            ->assertSee('Zero-value records do not require payout.')->assertDontSee('Mark as paid');
        $this->get(route('admin.commissions.index', ['staff_profile_id' => $this->staff->id], false))->assertOk()
            ->assertSee($this->staff->user->name)->assertSee('Archived');
        foreach ([$paid, $zero] as $record) {
            $this->patch(route('admin.commissions.pay', $record, false), ['paid_at' => today()->toDateString()])->assertSessionHasErrors('status');
        }
    }

    public function test_empty_and_filtered_empty_states_have_zero_totals_and_header_only_csv(): void
    {
        $this->actingAs($this->admin)->get(route('admin.commissions.index', absolute: false))
            ->assertOk()->assertSee('No commissions yet')->assertDontSee('Clear filters')
            ->assertViewHas('selectedCommission', null)->assertDontSee('data-pagination', false)
            ->assertViewHas('totals', fn ($totals) => (float) $totals['net'] === 0.0);
        $this->assertCount(1, $this->exportRows());
        $this->commission();
        $this->get(route('admin.commissions.index', ['status' => 'paid'], false))->assertOk()
            ->assertSee('No commissions found')->assertSee('Clear filters')->assertViewHas('selectedCommission', null);
    }

    public function test_csv_streams_across_batches_and_protects_text_without_changing_negative_amounts(): void
    {
        $this->staff->user->update(['name' => ' =SUM(1,1)']);
        $records = TherapistCommission::factory()->count(501)->create([
            'staff_profile_id' => $this->staff->id, 'appointment_id' => $this->appointment->id, 'transaction_id' => $this->transaction->id,
            'earned_at' => '2026-09-30 16:00:00', 'commission_type' => 'adjustment', 'commission_amount' => -44,
            'notes' => '@cmd', 'paid_by' => $this->admin->id,
        ]);
        $rows = $this->exportRows(['page' => 2, 'selected' => $records->first()->id]);
        $this->assertCount(502, $rows);
        $this->assertSame((string) $records->last()->id, $rows[1][0]);
        $this->assertSame((string) $records->first()->id, $rows[501][0]);
        $this->assertSame("' =SUM(1,1)", $rows[1][1]);
        $this->assertSame('-44.00', $rows[1][10]);
        $this->assertSame("'@cmd", $rows[1][14]);
        foreach (['+cmd', '-cmd', '@cmd', "\t=cmd"] as $name) {
            $this->staff->user->update(['name' => $name]);
            $this->assertSame("'".$name, $this->exportRows()[1][1]);
        }
    }

    public function test_list_and_export_validate_filters_and_other_roles_cannot_access_commissions(): void
    {
        $record = $this->commission();
        foreach (['admin.commissions.index', 'admin.commissions.export'] as $route) {
            $this->actingAs($this->admin)->getJson(route($route, ['status' => 'invalid', 'date_from' => '2026-09-30', 'date_to' => '2026-09-01', 'staff_profile_id' => 999999], false))
                ->assertUnprocessable()->assertJsonValidationErrors(['status', 'date_to', 'staff_profile_id']);
        }
        foreach (['staff', 'customer', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ([route('admin.commissions.index', absolute: false), route('admin.commissions.export', absolute: false), route('admin.commissions.show', $record, false)] as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->patch(route('admin.commissions.pay', $record, false), ['paid_at' => today()->toDateString()])->assertForbidden();
        }
    }

    private function commission(array $attributes = []): TherapistCommission
    {
        return TherapistCommission::factory()->create(array_merge([
            'staff_profile_id' => $this->staff->id, 'appointment_id' => $this->appointment->id,
            'transaction_id' => $this->transaction->id, 'earned_at' => '2026-09-30 16:00:00',
        ], $attributes));
    }

    private function exportRows(array $filters = []): array
    {
        $csv = $this->actingAs($this->admin)->get(route('admin.commissions.export', $filters, false))->assertOk()->assertDownload()->streamedContent();
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
