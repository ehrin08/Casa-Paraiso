<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_dashboard_has_real_zero_states_and_existing_action_routes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 14:00:00', 'Asia/Manila'));

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Thursday, October 1, 2026')
            ->assertSee('Today at Casa Paraiso')
            ->assertSee('₱0.00')
            ->assertSee('No overdue visits')
            ->assertSee('No upcoming confirmed visits')
            ->assertSee('No feedback yet.')
            ->assertSee('No confirmed or completed visits this week.')
            ->assertViewHas('weeklyMaximum', 1)
            ->assertViewHas('weeklyVisits', fn ($days) => $days->count() === 7 && $days->sum('count') === 0);

        foreach (['appointments.create', 'appointments.index', 'feedback.index', 'promotions.index', 'reports.index'] as $route) {
            $response->assertSee('href="'.route('admin.'.$route).'"', false);
        }
    }

    public function test_daily_metrics_use_manila_midnight_and_only_paid_revenue(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 00:00:00', 'Asia/Manila'));

        foreach (['2026-09-30 23:59:59', '2026-10-01 00:00:00', '2026-10-01 23:59:59', '2026-10-02 00:00:00'] as $time) {
            $appointment = $this->visit($time);
            Transaction::factory()->for($appointment)->create(['payment_status' => Transaction::PAYMENT_PAID, 'amount' => 100, 'paid_at' => $time]);
            Feedback::factory()->for($appointment)->create(['submitted_at' => $time]);
        }
        Transaction::factory()->for($appointment)->create(['payment_status' => Transaction::PAYMENT_PARTIAL, 'amount' => 900, 'paid_at' => now()]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['confirmedToday'] === 2
                && $summary['overdueConfirmed'] === 1
                && $summary['upcomingAppointments'] === 3
                && $summary['newFeedback'] === 2
                && (float) $summary['todayRevenue'] === 200.0);
    }

    public function test_upcoming_preview_is_limited_ordered_and_excludes_terminal_or_overdue_visits(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 13:00:00', 'Asia/Manila'));
        $expected = collect();
        foreach ([6, 4, 2, 5, 1, 3] as $hour) {
            $expected->push($this->visit(now()->addHours($hour)->toDateTimeString()));
        }
        $this->visit('2026-10-01 12:59:59');
        $this->visit('2026-10-01 13:01:00', Appointment::STATUS_COMPLETED);
        $this->visit('2026-10-01 13:02:00', Appointment::STATUS_CANCELLED);
        $this->visit('2026-10-01 13:03:00', Appointment::STATUS_NO_SHOW);
        $tomorrow = $this->visit('2026-10-02 13:00:00');
        $ids = $expected->sortBy('scheduled_start_at')->take(5)->pluck('id')->values()->all();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('upcomingAppointments', fn ($appointments) => $appointments->pluck('id')->all() === $ids)
            ->assertDontSee($tomorrow->appointment_number)
            ->assertSee('data-panel-link data-turbo="false"', false);
    }

    public function test_weekly_counts_include_completed_visits_and_respect_monday_boundaries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04 23:59:59', 'Asia/Manila'));
        $this->visit('2026-09-27 23:59:59');
        $this->visit('2026-09-28 00:00:00');
        $this->visit('2026-09-28 13:00:00', Appointment::STATUS_COMPLETED);
        $this->visit('2026-10-04 23:59:59');
        $this->visit('2026-10-05 00:00:00');
        $this->visit('2026-09-29 13:00:00', Appointment::STATUS_CANCELLED);
        $this->visit('2026-09-30 13:00:00', Appointment::STATUS_NO_SHOW);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('weeklyVisits', fn ($days) => $days->pluck('count')->all() === [2, 0, 0, 0, 0, 0, 1])
            ->assertViewHas('weeklyMaximum', 2)
            ->assertSee('Monday, Sep 28: 2 visits');

        $this->travelTo(CarbonImmutable::parse('2026-10-05 00:00:00', 'Asia/Manila'));
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('weeklyVisits', fn ($days) => $days->pluck('count')->all() === [1, 0, 0, 0, 0, 0, 0]);
    }

    public function test_latest_feedback_is_chosen_by_submission_date_and_safely_truncated(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 14:00:00', 'Asia/Manila'));
        $visit = $this->visit('2026-10-02 13:00:00');
        $latest = Feedback::factory()->for($visit)->create([
            'submitted_at' => now(),
            'comment' => '<script>alert(1)</script>'.str_repeat(' Relaxing care.', 30),
            'sentiment_label' => Feedback::SENTIMENT_NEGATIVE,
        ]);
        Feedback::factory()->create(['submitted_at' => now()->subDay(), 'comment' => 'Older submission']);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('latestFeedback', fn ($feedback) => $feedback->is($latest))
            ->assertSee('Negative')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($latest->comment)
            ->assertSee('href="'.route('admin.feedback.show', $latest).'"', false)
            ->assertSee('Oct 2, 2026');

        preg_match('/queries;desc="(\d+)"/', $response->headers->get('Server-Timing'), $matches);
        $this->assertLessThanOrEqual(15, (int) $matches[1], 'Populated dashboard exceeded its query budget.');
    }

    public function test_feedback_without_comment_does_not_invent_content(): void
    {
        Feedback::factory()->create(['comment' => null, 'sentiment_label' => Feedback::SENTIMENT_NEUTRAL]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('No written comment provided.')
            ->assertSee('Neutral');
    }

    private function visit(string $time, string $status = Appointment::STATUS_CONFIRMED): Appointment
    {
        $start = CarbonImmutable::parse($time, 'Asia/Manila');

        return Appointment::factory()->create([
            'status' => $status,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->addHour(),
        ]);
    }
}
