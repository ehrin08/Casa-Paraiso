<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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

    public function test_customer_feedback_records_a_gemini_shadow_run(): void
    {
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [
            ['text' => '{"label":"positive","confidence":0.92}'],
        ]]]]])]);
        Config::set('sentiment.mode', 'shadow');
        Config::set('sentiment.model_driver', 'gemini');
        Config::set('sentiment.gemini.key', 'test-gemini-key');

        $customerUser = User::factory()->customer()->create();
        $customer = CustomerProfile::factory()->for($customerUser)->create();
        $appointment = Appointment::factory()
            ->for($customer)
            ->for(Service::factory()->create())
            ->create([
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => now()->subHour(),
            ]);

        $this->actingAs($customerUser)
            ->post(route('customer.feedback.store', absolute: false), [
                'appointment_id' => $appointment->id,
                'rating' => 5,
                'comment' => 'Sulit na sulit ang massage.',
            ])
            ->assertRedirect(route('customer.feedback.index', absolute: false));

        $feedback = $appointment->feedback;
        $this->assertSame('rules', $feedback->sentiment_source);
        $this->assertDatabaseHas('feedback_sentiment_runs', [
            'feedback_id' => $feedback->id,
            'source' => 'model',
            'classifier_version' => 'gemini-v1',
            'label' => Feedback::SENTIMENT_POSITIVE,
            'is_authoritative' => false,
        ]);
    }
}
