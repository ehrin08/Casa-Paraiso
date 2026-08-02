<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SentimentUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_feedback_persists_a_tagalog_sentiment_label_and_score(): void
    {
        $customerUser = User::factory()->customer()->create();
        $customer = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create();
        $appointment = Appointment::factory()
            ->for($customer)
            ->for($service)
            ->create([
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => now()->subHour(),
            ]);

        $this->actingAs($customerUser)
            ->post(route('customer.feedback.store', absolute: false), [
                'appointment_id' => $appointment->id,
                'rating' => 5,
                'comment' => 'Napakaganda ng serbisyo at mabait ang therapist.',
            ])
            ->assertRedirect(route('customer.feedback.index', absolute: false));

        $this->assertDatabaseHas('feedback', [
            'appointment_id' => $appointment->id,
            'sentiment_label' => Feedback::SENTIMENT_POSITIVE,
            'sentiment_score' => '1.00',
            'sentiment_analysis_version' => '2.0.0',
        ]);
        $this->assertDatabaseHas('feedback_topics', [
            'feedback_id' => $appointment->feedback->id,
            'topic_key' => 'therapist_service',
            'polarity' => 'positive',
        ]);
        $this->assertDatabaseHas('feedback_sentiment_runs', [
            'feedback_id' => $appointment->feedback->id,
            'source' => 'rules',
            'is_authoritative' => true,
        ]);
        $this->assertDatabaseHas('feedback_sentiment_runs', [
            'feedback_id' => $appointment->feedback->id,
            'source' => 'model',
            'is_authoritative' => false,
        ]);
    }
}
