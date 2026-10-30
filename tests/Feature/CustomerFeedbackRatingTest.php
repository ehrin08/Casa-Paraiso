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

class CustomerFeedbackRatingTest extends TestCase
{
    use RefreshDatabase;

    private User $customerUser;

    private CustomerProfile $customer;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerUser = User::factory()->customer()->create();
        $this->customer = CustomerProfile::factory()->for($this->customerUser)->create();
        $this->appointment = Appointment::factory()
            ->for($this->customer)
            ->for(Service::factory())
            ->create([
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => now()->subHour(),
            ]);
    }

    public function test_feedback_form_renders_an_unselected_star_radio_group(): void
    {
        foreach (['customer.feedback.create', 'customer.feedback.index'] as $route) {
            $html = $this->actingAs($this->customerUser)
                ->get(route($route, absolute: false))
                ->assertOk()
                ->assertSee('data-star-rating', false)
                ->assertSee('<legend class="casa-label">Rating</legend>', false)
                ->assertDontSee('<select id="rating"', false)
                ->getContent();

            for ($star = 1; $star <= 5; $star++) {
                $this->assertMatchesRegularExpression('/<input type="radio" id="rating-'.$star.'" name="rating" value="'.$star.'"[^>]*required/', $html);
            }

            $this->assertDoesNotMatchRegularExpression('/name="rating"[^>]*checked/', $html);
        }
    }

    public function test_failed_submission_restores_the_chosen_star(): void
    {
        $this->actingAs($this->customerUser)
            ->from(route('customer.feedback.create', absolute: false))
            ->post(route('customer.feedback.store', absolute: false), ['rating' => 4, 'comment' => 'Relaxing.'])
            ->assertRedirect(route('customer.feedback.create', absolute: false))
            ->assertSessionHasErrors('appointment_id');

        $html = $this->actingAs($this->customerUser)
            ->get(route('customer.feedback.create', absolute: false))
            ->getContent();

        $this->assertMatchesRegularExpression('/id="rating-4" name="rating" value="4"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="rating-[1235]"[^>]*checked/', $html);
    }

    public function test_rating_is_required_and_stored_from_the_chosen_star(): void
    {
        $this->actingAs($this->customerUser)
            ->from(route('customer.feedback.create', absolute: false))
            ->post(route('customer.feedback.store', absolute: false), ['appointment_id' => $this->appointment->id])
            ->assertSessionHasErrors('rating');

        $this->actingAs($this->customerUser)
            ->get(route('customer.feedback.create', absolute: false))
            ->assertSee('aria-describedby="rating-error"', false)
            ->assertSee('id="rating-error"', false);

        $this->actingAs($this->customerUser)
            ->post(route('customer.feedback.store', absolute: false), [
                'appointment_id' => $this->appointment->id,
                'rating' => 3,
            ])
            ->assertRedirect(route('customer.feedback.index', absolute: false));

        $this->assertDatabaseHas('feedback', ['appointment_id' => $this->appointment->id, 'rating' => 3]);
    }

    public function test_read_only_views_show_stars_instead_of_a_fraction(): void
    {
        $staff = StaffProfile::factory()->create();
        $this->appointment->update(['staff_profile_id' => $staff->id]);
        Feedback::factory()->create([
            'customer_profile_id' => $this->customer->id,
            'appointment_id' => $this->appointment->id,
            'service_id' => $this->appointment->service_id,
            'rating' => 4,
        ]);

        $this->actingAs($this->customerUser)
            ->get(route('customer.feedback.index', absolute: false))
            ->assertOk()
            ->assertSee('aria-label="4 out of 5 stars"', false)
            ->assertDontSee('4/5');

        $this->actingAs($staff->user)
            ->get(route('staff.feedback.index', absolute: false))
            ->assertOk()
            ->assertSee('aria-label="4 out of 5 stars"', false)
            ->assertDontSee('4/5');
    }
}
