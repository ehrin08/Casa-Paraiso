<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\User;
use App\View\Components\CustomerFeedbackPrompt;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFeedbackPromptTest extends TestCase
{
    use RefreshDatabase;

    private const PROMPT_MARKER = 'data-modal-name="'.CustomerFeedbackPrompt::MODAL_NAME.'"';

    private User $customerUser;

    private CustomerProfile $customer;

    private Appointment $older;

    private Appointment $latest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerUser = User::factory()->customer()->create();
        $this->customer = CustomerProfile::factory()->for($this->customerUser)->create();
        $this->older = $this->completedVisit(now()->subDays(3));
        $this->latest = $this->completedVisit(now()->subHour());
    }

    public function test_prompt_opens_on_customer_pages_with_the_latest_visit_preselected(): void
    {
        $urls = [
            route('customer.appointments.index', absolute: false),
            route('customer.appointments.history', absolute: false),
            route('customer.appointments.show', $this->older, false),
        ];

        foreach ($urls as $url) {
            $html = $this->actingAs($this->customerUser)->get($url)->assertOk()
                ->assertSee(self::PROMPT_MARKER, false)
                ->assertSee('How was your visit?')
                ->assertSee('action="'.route('customer.feedback.prompt.dismiss').'"', false)
                ->getContent();

            $this->assertMatchesRegularExpression('/<option value="'.$this->latest->id.'"\s+selected/', $html);
            $this->assertMatchesRegularExpression('/<option value="'.$this->older->id.'"\s*>/', $html);
        }
    }

    public function test_prompt_stays_closed_on_feedback_and_booking_pages(): void
    {
        foreach (['customer.feedback.index', 'customer.feedback.create', 'customer.appointments.create'] as $route) {
            $this->actingAs($this->customerUser)
                ->get(route($route, absolute: false))
                ->assertOk()
                ->assertDontSee(self::PROMPT_MARKER, false);
        }
    }

    public function test_prompt_stays_closed_without_an_eligible_visit(): void
    {
        foreach ([$this->older, $this->latest] as $appointment) {
            Feedback::factory()->create([
                'customer_profile_id' => $this->customer->id,
                'appointment_id' => $appointment->id,
                'service_id' => $appointment->service_id,
            ]);
        }

        $this->actingAs($this->customerUser)
            ->get(route('customer.appointments.index', absolute: false))
            ->assertOk()
            ->assertDontSee(self::PROMPT_MARKER, false);
    }

    public function test_prompt_is_only_for_customers(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard', absolute: false))
            ->assertOk()
            ->assertDontSee(self::PROMPT_MARKER, false);
    }

    public function test_later_dismisses_the_prompt_for_the_session(): void
    {
        $page = route('customer.appointments.index', absolute: false);

        $this->actingAs($this->customerUser)
            ->from($page)
            ->post(route('customer.feedback.prompt.dismiss', absolute: false))
            ->assertRedirect($page)
            ->assertSessionHas(CustomerFeedbackPrompt::DISMISSED_SESSION_KEY, true);

        $this->actingAs($this->customerUser)
            ->get($page)
            ->assertOk()
            ->assertDontSee(self::PROMPT_MARKER, false)
            ->assertSee('data-feedback-visit', false);
    }

    public function test_failed_prompt_submission_reopens_the_prompt_with_errors(): void
    {
        $page = route('customer.appointments.index', absolute: false);

        $this->actingAs($this->customerUser)
            ->from($page)
            ->post(route('customer.feedback.store', absolute: false), [
                '_modal' => CustomerFeedbackPrompt::MODAL_NAME,
                'appointment_id' => $this->latest->id,
            ])
            ->assertRedirect($page)
            ->assertSessionHasErrors('rating');

        $this->actingAs($this->customerUser)
            ->get($page)
            ->assertSee(self::PROMPT_MARKER, false)
            ->assertSee('id="rating-error"', false);
    }

    private function completedVisit(DateTimeInterface $completedAt): Appointment
    {
        return Appointment::factory()
            ->for($this->customer)
            ->for(Service::factory())
            ->create([
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => $completedAt,
            ]);
    }
}
