<?php

namespace Tests\Feature;

use App\Models\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclassifySentimentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_changes_without_modifying_feedback(): void
    {
        $feedback = Feedback::factory()->create([
            'rating' => 5,
            'comment' => 'Napakaganda ng serbisyo at mabait ang therapist.',
            'sentiment_label' => Feedback::SENTIMENT_NEGATIVE,
            'sentiment_score' => -1,
        ]);

        $this->artisan('casa:reclassify-sentiment')
            ->expectsOutputToContain('Feedback records analyzed: 1')
            ->expectsOutputToContain('Feedback records that would change: 1')
            ->expectsOutputToContain('Dry run only.')
            ->assertSuccessful();

        $this->assertDatabaseHas('feedback', [
            'id' => $feedback->id,
            'comment' => $feedback->comment,
            'appointment_id' => $feedback->appointment_id,
            'customer_profile_id' => $feedback->customer_profile_id,
            'service_id' => $feedback->service_id,
            'sentiment_label' => Feedback::SENTIMENT_NEGATIVE,
            'sentiment_score' => '-1.00',
        ]);
    }

    public function test_apply_updates_changed_records_and_is_idempotent(): void
    {
        $feedback = Feedback::factory()->create([
            'rating' => 5,
            'comment' => 'Napakaganda ng serbisyo at mabait ang therapist.',
            'sentiment_label' => Feedback::SENTIMENT_NEGATIVE,
            'sentiment_score' => -1,
        ]);

        $this->artisan('casa:reclassify-sentiment --apply')
            ->expectsOutputToContain('Feedback records updated: 1')
            ->expectsOutputToContain('Sentiment reclassification applied.')
            ->assertSuccessful();

        $this->assertDatabaseHas('feedback', [
            'id' => $feedback->id,
            'sentiment_label' => Feedback::SENTIMENT_POSITIVE,
            'sentiment_score' => '1.00',
        ]);

        $this->artisan('casa:reclassify-sentiment --apply')
            ->expectsOutputToContain('Feedback records updated: 0')
            ->assertSuccessful();
    }
}
