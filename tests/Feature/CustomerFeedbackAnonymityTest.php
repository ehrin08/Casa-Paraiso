<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFeedbackAnonymityTest extends TestCase
{
    use RefreshDatabase;

    private User $customerUser;

    private CustomerProfile $customer;

    private StaffProfile $staff;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerUser = User::factory()->customer()->create(['name' => 'Marisol Hidden']);
        $this->customer = CustomerProfile::factory()->for($this->customerUser)->create();
        $this->staff = StaffProfile::factory()->create();
        $this->appointment = Appointment::factory()
            ->for($this->customer)
            ->for(Service::factory()->state(['name' => 'Hilot Ritual']))
            ->create([
                'staff_profile_id' => $this->staff->id,
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => now()->subHour(),
            ]);
    }

    public function test_customer_can_submit_feedback_hidden_from_the_therapist(): void
    {
        $this->actingAs($this->customerUser)
            ->get(route('customer.feedback.create', absolute: false))
            ->assertOk()
            ->assertSee('name="is_anonymous"', false)
            ->assertSee('Hide my name from my therapist');

        $this->actingAs($this->customerUser)
            ->post(route('customer.feedback.store', absolute: false), [
                'appointment_id' => $this->appointment->id,
                'rating' => 5,
                'comment' => 'Very calm.',
                'is_anonymous' => '1',
            ])
            ->assertRedirect(route('customer.feedback.index', absolute: false));

        $this->assertDatabaseHas('feedback', [
            'appointment_id' => $this->appointment->id,
            'customer_profile_id' => $this->customer->id,
            'is_anonymous' => true,
        ]);

        $this->actingAs($this->customerUser)
            ->get(route('customer.feedback.index', absolute: false))
            ->assertSee('Hidden from therapist');
    }

    public function test_feedback_is_named_by_default(): void
    {
        $this->actingAs($this->customerUser)
            ->post(route('customer.feedback.store', absolute: false), [
                'appointment_id' => $this->appointment->id,
                'rating' => 4,
            ]);

        $this->assertDatabaseHas('feedback', ['appointment_id' => $this->appointment->id, 'is_anonymous' => false]);
    }

    public function test_therapist_pages_hide_the_name_of_anonymous_feedback(): void
    {
        $feedback = $this->anonymousFeedback();

        $this->actingAs($this->staff->user)
            ->get(route('staff.feedback.index', absolute: false))
            ->assertOk()
            ->assertSee('Anonymous customer')
            ->assertDontSee('Marisol Hidden');

        $this->actingAs($this->staff->user)
            ->get(route('staff.feedback.index', ['sort' => 'customer', 'direction' => 'asc'], false))
            ->assertOk()
            ->assertDontSee('Marisol Hidden');

        $this->actingAs($this->staff->user)
            ->get(route('staff.feedback.index', ['q' => 'Marisol'], false))
            ->assertOk()
            ->assertViewHas('feedback', fn ($items) => $items->total() === 0);

        $this->actingAs($this->staff->user)
            ->get(route('staff.feedback.index', ['q' => 'Hilot'], false))
            ->assertViewHas('feedback', fn ($items) => $items->total() === 1);

        $this->actingAs($this->staff->user)
            ->get(route('staff.feedback.show', $feedback, false))
            ->assertOk()
            ->assertSee('Anonymous customer')
            ->assertDontSee('Marisol Hidden');
    }

    public function test_therapist_customer_pages_leave_out_anonymous_feedback(): void
    {
        $this->anonymousFeedback();

        $this->actingAs($this->staff->user)
            ->get(route('staff.customers.show', $this->customer, false))
            ->assertOk()
            ->assertViewHas('customer', fn ($customer) => $customer->feedback_count === 0 && $customer->feedback->isEmpty())
            ->assertDontSee('Anonymous thoughts.');

        $this->actingAs($this->staff->user)
            ->get(route('staff.customers.index', absolute: false))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->firstWhere('id', $this->customer->id)?->feedback_count === 0);
    }

    public function test_admins_still_see_the_name_with_an_anonymous_badge(): void
    {
        $feedback = $this->anonymousFeedback();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.feedback.index', absolute: false))
            ->assertOk()
            ->assertSee('Marisol Hidden')
            ->assertSee('Anonymous');

        $this->actingAs($admin)
            ->get(route('admin.feedback.show', $feedback, false))
            ->assertOk()
            ->assertSee('Marisol Hidden')
            ->assertSee('Hidden from the therapist.');

        $csv = $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => 'feedback', 'range' => 'all'], false))
            ->assertOk()
            ->streamedContent();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));

        $this->assertSame('Hidden From Therapist', end($rows[0]));
        $this->assertSame('yes', end($rows[1]));
    }

    private function anonymousFeedback(): Feedback
    {
        return Feedback::factory()->create([
            'customer_profile_id' => $this->customer->id,
            'appointment_id' => $this->appointment->id,
            'service_id' => $this->appointment->service_id,
            'comment' => 'Anonymous thoughts.',
            'is_anonymous' => true,
        ]);
    }
}
