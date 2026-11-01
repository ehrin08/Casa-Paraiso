<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackMassageServiceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_the_service_once_and_assigns_gaia_touch_therapists(): void
    {
        $gaia = Service::factory()->create(['slug' => 'gaia-touch', 'name' => 'GAIA TOUCH']);
        $gaiaTherapist = StaffProfile::factory()->create();
        $otherTherapist = StaffProfile::factory()->create();
        $gaiaTherapist->services()->attach($gaia);
        $otherTherapist->services()->attach(Service::factory()->create());

        $this->migration()->up();
        $this->migration()->up();

        $service = Service::query()->where('slug', 'back-massage')->sole();
        $this->assertSame(['30-Minute Back Massage', 30, true], [$service->name, $service->duration_minutes, $service->is_active]);
        $this->assertEquals(299, $service->price);
        $this->assertSame([$gaiaTherapist->id], $service->staffProfiles()->pluck('staff_profiles.id')->all());
    }

    public function test_fresh_databases_without_the_catalog_are_left_to_the_seeder(): void
    {
        $this->migration()->up();

        $this->assertDatabaseMissing('services', ['slug' => 'back-massage']);
    }

    public function test_rollback_keeps_a_service_that_has_bookings(): void
    {
        Service::factory()->create(['slug' => 'gaia-touch']);
        $this->migration()->up();
        $service = Service::query()->where('slug', 'back-massage')->sole();
        Appointment::factory()->for($service)->create();

        $this->migration()->down();
        $this->assertModelExists($service);

        Appointment::query()->delete();
        $this->migration()->down();
        $this->assertModelMissing($service);
    }

    public function test_thirty_minute_service_can_start_at_ten_thirty_and_finish_at_closing(): void
    {
        $service = Service::factory()->create(['slug' => 'back-massage', 'duration_minutes' => 30]);
        $staff = StaffProfile::factory()->for(User::factory()->staff())->create();
        $staff->services()->attach($service);
        $day = now('Asia/Manila')->addWeek();
        StaffWeeklySchedule::factory()->for($staff)->create(['day_of_week' => $day->dayOfWeek, 'start_time' => '13:00:00', 'end_time' => '00:00:00', 'ends_next_day' => true]);

        $slots = collect($this->actingAs(User::factory()->customer()->create())
            ->getJson(route('customer.appointments.availability', ['service_id' => $service->id, 'month' => $day->format('Y-m')]))
            ->assertOk()->json('dates.'.$day->toDateString()));

        $this->assertSame('22:30', $slots->last()['time']);
        $this->assertSame($day->toDateString().' 23:00:00', $slots->last()['ends_at']);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_02_000000_add_back_massage_service.php');
    }
}
