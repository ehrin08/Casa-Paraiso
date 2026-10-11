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
use Tests\TestCase;

class AdminCustomerModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_super_admin_open_links_preserve_list_context_and_name_links(): void
    {
        $customer = CustomerProfile::factory()->create();
        $filters = ['q' => $customer->customer_code, 'sort' => 'name', 'direction' => 'desc', 'page' => 1];
        foreach ($this->administrators() as $admin) {
            $this->actingAs($admin)->get(route('admin.customers.index', $filters))
                ->assertOk()
                ->assertSee(route('admin.customers.show', $customer), false)
                ->assertSee(e(route('admin.customers.show', ['customer' => $customer, 'modal' => 1, 'return_filters' => $filters])), false)
                ->assertSee('data-customer-open="'.$customer->id.'"', false)
                ->assertSee('aria-haspopup="dialog"', false)
                ->assertDontSee('data-customer-detail-recovery', false);
        }
    }

    public function test_only_explicit_modal_requests_return_shell_free_details_with_actions(): void
    {
        $customer = CustomerProfile::factory()->create(['notes' => 'Prefers quiet treatments.']);
        foreach ($this->administrators() as $admin) {
            $this->actingAs($admin);
            $this->get(route('admin.customers.show', ['customer' => $customer, 'modal' => 1]), ['X-Casa-Panel' => '1'])
                ->assertOk()->assertSee('data-page-content', false)->assertSee('data-customer-detail', false)
                ->assertSee($customer->display_name)->assertSee($customer->display_email)
                ->assertSee('Contact details')->assertSee('Appointments')->assertSee('Transactions')
                ->assertSee('Feedback')->assertSee('Customer rewards')->assertSee('Prefers quiet treatments.')
                ->assertSee('Add appointment')->assertSee('Update notes')->assertSee('data-customer-note-form', false)
                ->assertSee('name="return_to" value="index"', false)
                ->assertDontSee('<html', false)->assertDontSee('data-panel-host', false)
                ->assertDontSee('data-modal-name', false);

            foreach ([
                [[], []],
                [['modal' => 1], []],
                [[], ['X-Casa-Panel' => '1']],
            ] as [$parameters, $headers]) {
                $this->get(route('admin.customers.show', ['customer' => $customer] + $parameters), $headers)
                    ->assertOk()->assertSee('<html', false)->assertSee('All customers')
                    ->assertDontSee('data-customer-detail', false);
            }
        }
    }

    public function test_guests_empty_histories_and_customer_text_remain_safe(): void
    {
        $guest = CustomerProfile::factory()->create([
            'user_id' => null, 'guest_name' => '<script>alert("guest")</script>',
            'guest_phone' => '09123456789', 'notes' => '<img src=x onerror=alert(1)>',
        ]);
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.customers.show', ['customer' => $guest, 'modal' => 1]), ['X-Casa-Panel' => '1'])
            ->assertOk()->assertSee($guest->guest_name)->assertSee($guest->notes)
            ->assertSee('09123456789')->assertSee('No records yet')
            ->assertDontSee($guest->guest_name, false)->assertDontSee($guest->notes, false);
    }

    public function test_modal_keeps_total_counts_and_latest_eight_history_records(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();
        $therapist = StaffProfile::factory()->create();
        $visits = collect(range(1, 9))->map(fn ($day) => Appointment::factory()->create([
            'customer_profile_id' => $customer->id, 'service_id' => $service->id,
            'staff_profile_id' => $therapist->id, 'requested_start_at' => "2026-10-{$day} 15:00:00",
        ]));
        $payment = Transaction::factory()->create(['customer_profile_id' => $customer->id, 'service_id' => $service->id, 'appointment_id' => $visits->last()->id]);
        $feedback = Feedback::factory()->create(['customer_profile_id' => $customer->id, 'service_id' => $service->id, 'appointment_id' => $visits->last()->id, 'comment' => 'Customer modal review.']);
        $reward = PromotionSuggestion::factory()->create(['customer_profile_id' => $customer->id]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.customers.show', ['customer' => $customer, 'modal' => 1]), ['X-Casa-Panel' => '1'])
            ->assertOk()->assertSee($visits->last()->appointment_number)
            ->assertDontSee($visits->first()->appointment_number)
            ->assertSee($payment->transaction_number)->assertSee($feedback->comment)->assertSee($reward->suggested_offer);
        $this->assertSame(9, $response->viewData('customer')->appointments_count);
        $this->assertCount(8, $response->viewData('customer')->appointments);
    }

    public function test_modal_notes_save_returns_to_filtered_list_and_reopens_only_once(): void
    {
        $customer = CustomerProfile::factory()->create(['notes' => 'Original care note.']);
        $filters = ['q' => 'Unmatched search', 'status' => 'active', 'sort' => 'appointments', 'direction' => 'desc', 'page' => 2];
        foreach ($this->administrators() as $admin) {
            $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
                'notes' => 'Saved in the customer modal.', 'return_to' => 'index', 'return_filters' => $filters,
                'address' => 'Do not persist navigation or unrelated fields.',
            ])->assertRedirect(route('admin.customers.index', $filters))
                ->assertSessionHas('customer_modal_id', $customer->id)
                ->assertSessionHas('status', 'customer-updated');
            $this->assertSame('Saved in the customer modal.', $customer->fresh()->notes);
            $this->assertSame($customer->address, $customer->fresh()->address);

            $this->get(route('admin.customers.index', $filters))->assertOk()
                ->assertSee('No customers found')->assertSee('data-customer-detail-recovery', false)
                ->assertSee('Saved in the customer modal.')->assertSee('return_filters[page]', false);
            $this->get(route('admin.customers.index', $filters))->assertOk()
                ->assertDontSee('data-customer-detail-recovery', false);
        }
    }

    public function test_note_validation_returns_input_and_errors_to_the_same_customer_modal(): void
    {
        $customer = CustomerProfile::factory()->create(['notes' => 'Keep this note.']);
        $filters = ['q' => $customer->customer_code, 'status' => 'active', 'sort' => 'name', 'direction' => 'asc', 'page' => 1];
        $invalidNotes = str_repeat('x', 5001);
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.customers.update', $customer), [
            'notes' => $invalidNotes, '_modal' => 'admin-customer-notes-'.$customer->id,
            'return_to' => 'index', 'return_filters' => $filters,
        ])->assertRedirect(route('admin.customers.index', $filters))->assertSessionHasErrors('notes')
            ->assertSessionHas('customer_modal_id', $customer->id);
        $this->assertSame('Keep this note.', $customer->fresh()->notes);
        $this->get(route('admin.customers.index', $filters))->assertOk()
            ->assertSee('data-customer-detail-recovery', false)->assertSee('data-customer-note-invalid', false)
            ->assertSee($invalidNotes)->assertSee('5000 characters');
    }

    public function test_invalid_navigation_context_cannot_redirect_externally_or_change_notes(): void
    {
        $customer = CustomerProfile::factory()->create(['notes' => 'Unchanged.']);
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.customers.update', $customer), [
            'notes' => 'Rejected.', 'return_to' => 'index',
            'return_filters' => ['q' => ['bad'], 'page' => -2, 'url' => 'https://example.org'],
            '_modal' => 'admin-customer-notes-'.$customer->id,
        ])->assertRedirect(route('admin.customers.index'))->assertSessionHasErrors();
        $this->assertSame('Unchanged.', $customer->fresh()->notes);
    }

    public function test_non_text_notes_recover_without_breaking_the_list(): void
    {
        $customer = CustomerProfile::factory()->create(['notes' => 'Unchanged.']);
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.customers.update', $customer), [
            'notes' => ['invalid'], 'return_to' => 'index', '_modal' => 'admin-customer-notes-'.$customer->id,
        ])->assertRedirect(route('admin.customers.index'))->assertSessionHasErrors('notes');
        $this->get(route('admin.customers.index'))->assertOk()->assertSee('data-customer-note-invalid', false);
        $this->assertSame('Unchanged.', $customer->fresh()->notes);
    }

    public function test_standalone_note_updates_keep_the_detail_redirect_and_clear_notes(): void
    {
        $customer = CustomerProfile::factory()->create(['notes' => 'Clear this note.']);
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.customers.update', $customer), ['notes' => ''])
            ->assertRedirect(route('admin.customers.show', $customer))->assertSessionMissing('customer_modal_id');
        $this->assertNull($customer->fresh()->notes);
    }

    public function test_customer_modal_and_note_updates_reject_other_roles_and_missing_records(): void
    {
        $customer = CustomerProfile::factory()->create();
        foreach ([User::factory()->customer()->create(), User::factory()->staff()->create(), User::factory()->receptionist()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.customers.show', ['customer' => $customer, 'modal' => 1]), ['X-Casa-Panel' => '1'])->assertForbidden();
            $this->patch(route('admin.customers.update', $customer), ['notes' => 'Denied.'])->assertForbidden();
        }
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.customers.show', ['customer' => 999999, 'modal' => 1]), ['X-Casa-Panel' => '1'])->assertNotFound();
        $this->withSession(['customer_modal_id' => 999999])->get(route('admin.customers.index'))->assertOk()
            ->assertDontSee('data-customer-detail-recovery', false);
    }

    private function administrators(): array
    {
        return [
            User::factory()->admin()->create(),
            User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'email' => config('auth.super_admin_email')]),
        ];
    }
}
