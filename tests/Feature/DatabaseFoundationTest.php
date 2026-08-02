<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionRule;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffWeeklySchedule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_operational_foundation_exists(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@casaparaiso.test',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->assertDatabaseCount('services', 4);
        $this->assertDatabaseHas('services', [
            'name' => 'GAIA TOUCH',
            'slug' => 'gaia-touch',
            'duration_minutes' => 60,
            'price' => 499.00,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('services', [
            'name' => 'AURORA BREEZE',
            'slug' => 'aurora-breeze',
            'duration_minutes' => 120,
            'price' => 849.00,
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('rfm_segments', 5);
        $this->assertDatabaseCount('promotion_rules', 0);
        $this->assertGreaterThanOrEqual(2, StaffProfile::query()->count());
        $this->assertGreaterThanOrEqual(10, StaffWeeklySchedule::query()->count());
        $this->assertGreaterThanOrEqual(5, DB::table('staff_services')->count());
    }

    public function test_staff_customer_service_and_schedule_relationships_work(): void
    {
        $staffUser = User::factory()->staff()->create();
        $customerUser = User::factory()->customer()->create();
        $staffProfile = StaffProfile::factory()->for($staffUser)->create();
        $customerProfile = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create();

        $staffProfile->services()->attach($service);
        $schedule = StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => StaffWeeklySchedule::MONDAY,
        ]);
        $exception = StaffScheduleException::factory()
            ->for($staffProfile)
            ->for($staffUser, 'creator')
            ->create([
                'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
            ]);

        $this->assertTrue($staffUser->staffProfile->is($staffProfile));
        $this->assertTrue($customerUser->customerProfile->is($customerProfile));
        $this->assertTrue($staffProfile->services->contains($service));
        $this->assertTrue($service->staffProfiles->contains($staffProfile));
        $this->assertTrue($staffProfile->weeklySchedules->contains($schedule));
        $this->assertTrue($staffProfile->scheduleExceptions->contains($exception));
        $this->assertSame(StaffScheduleException::TYPES, ['available', 'unavailable']);
    }

    public function test_appointment_transaction_feedback_and_status_relationships_work(): void
    {
        $staffUser = User::factory()->staff()->create();
        $customerProfile = CustomerProfile::factory()->create();
        $staffProfile = StaffProfile::factory()->for($staffUser)->create();
        $service = Service::factory()->create();

        $appointment = Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->for($staffProfile)
            ->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->addDay()->setTime(10, 0),
                'scheduled_end_at' => now()->addDay()->setTime(11, 0),
            ]);

        $statusLog = AppointmentStatusLog::factory()
            ->for($appointment)
            ->for($staffUser, 'changedBy')
            ->create([
                'from_status' => null,
                'to_status' => Appointment::STATUS_CONFIRMED,
            ]);

        $transaction = Transaction::factory()
            ->for($customerProfile)
            ->for($appointment)
            ->for($service)
            ->for($staffUser, 'recorder')
            ->create([
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_GCASH,
            ]);

        $feedback = Feedback::factory()
            ->for($customerProfile)
            ->for($appointment)
            ->for($service)
            ->create([
                'rating' => 5,
                'sentiment_label' => Feedback::SENTIMENT_POSITIVE,
            ]);

        $this->assertSame(Appointment::STATUSES, ['confirmed', 'completed', 'cancelled', 'no_show']);
        $this->assertSame(Transaction::PAYMENT_STATUSES, ['unpaid', 'partial', 'paid', 'refunded', 'void']);
        $this->assertSame(Feedback::SENTIMENT_LABELS, ['positive', 'neutral', 'negative']);
        $this->assertTrue($appointment->customerProfile->is($customerProfile));
        $this->assertTrue($appointment->service->is($service));
        $this->assertTrue($appointment->staffProfile->is($staffProfile));
        $this->assertTrue($appointment->statusLogs->contains($statusLog));
        $this->assertTrue($appointment->transactions->contains($transaction));
        $this->assertTrue($appointment->feedback->is($feedback));
        $this->assertTrue($transaction->recorder->is($staffUser));
    }

    public function test_rfm_promotion_relationships_and_statuses_work(): void
    {
        $admin = User::factory()->admin()->create();
        $customerProfile = CustomerProfile::factory()->create();
        $segment = RfmSegment::factory()->create(['name' => 'High-value test customer']);
        $rule = PromotionRule::factory()->for($segment)->create([
            'suggested_offer' => 'Premium wellness package perk',
        ]);

        $suggestion = PromotionSuggestion::factory()
            ->for($customerProfile)
            ->for($segment, 'rfmSegment')
            ->for($rule, 'promotionRule')
            ->for($admin, 'reviewer')
            ->create([
                'status' => PromotionSuggestion::STATUS_REVIEWED,
                'reviewed_at' => now(),
            ]);

        $this->assertSame(PromotionSuggestion::STATUSES, ['suggested', 'reviewed', 'applied', 'dismissed']);
        $this->assertTrue($segment->promotionRules->contains($rule));
        $this->assertTrue($segment->promotionSuggestions->contains($suggestion));
        $this->assertTrue($rule->promotionSuggestions->contains($suggestion));
        $this->assertTrue($customerProfile->promotionSuggestions->contains($suggestion));
        $this->assertTrue($suggestion->reviewer->is($admin));
    }
}
