<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleWeek;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use App\Services\AppointmentWorkflow;
use App\Services\WeeklyRoster;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RosterConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private array $workers = [];

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertSame('testing', DB::selectOne('select database() as name')->name);
        $this->assertStringContainsString('MariaDB', DB::selectOne('select version() as version')->version);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::selectOne('select database() as name')->name);
        $this->assertStringContainsString('MariaDB', DB::selectOne('select version() as version')->version);
        $this->travelTo(Carbon::parse('2026-10-18 10:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($this->workers as [$process, $directory]) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
            if (str_starts_with(realpath($directory), realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'casa-roster-')) {
                File::deleteDirectory($directory);
            }
        }
        $this->beforeTruncatingDatabase();
        $this->truncateTablesForAllConnections();
        // Truncation clears migration-seeded catalog rows; rebuild the next test's baseline.
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_concurrent_duplicate_shift_submissions_cannot_create_overlapping_rows(): void
    {
        [$staff, , , $week] = $this->fixture();
        $data = ['staff_profile_id' => $staff->id, 'schedule_date' => '2026-10-18', 'start_time' => '18:00', 'end_time' => '19:00', 'ends_next_day' => false];
        $worker = $this->worker(['operation' => 'shift', 'week_id' => $week->id, 'revision' => 1, 'shift' => $data]);
        DB::beginTransaction();
        StaffScheduleWeek::whereKey($week->id)->lockForUpdate()->firstOrFail();
        $this->releaseWorker($worker);
        $this->waitForRowLock($worker, 'staff_schedule_weeks');
        app(WeeklyRoster::class)->saveShift($week, $data, 1);
        DB::commit();
        $this->assertSame('validation', $this->workerResult($worker)['status']);
        $this->assertSame(2, $week->shifts()->where('version', 'draft')->count());
        $this->assertSame(2, $week->fresh()->revision);
    }

    public function test_stale_bulk_replacement_cannot_overwrite_another_admins_committed_hours(): void
    {
        [$staff, , , $week] = $this->fixture();
        $data = ['mode' => 'hours', 'staff_ids' => [$staff->id], 'dates' => ['2026-10-18'], 'start_time' => '15:00', 'end_time' => '16:00'];
        $worker = $this->worker(['operation' => 'bulk', 'week_id' => $week->id, 'revision' => 1, 'bulk' => $data]);
        DB::beginTransaction();
        StaffScheduleWeek::whereKey($week->id)->lockForUpdate()->firstOrFail();
        $this->releaseWorker($worker);
        $this->waitForRowLock($worker, 'staff_schedule_weeks');
        app(WeeklyRoster::class)->bulkReplace($week, [...$data, 'start_time' => '14:00', 'end_time' => '17:00'], 1);
        DB::commit();
        $result = $this->workerResult($worker);
        $this->assertSame('validation', $result['status']);
        $this->assertContains('roster', $result['fields']);
        $this->assertSame('14:00:00', $week->shifts()->sole()->start_time);
    }

    public function test_publisher_waiting_for_a_booking_sees_the_new_reservation_and_blocks(): void
    {
        [$staff, $service, $customer, $week, $admin] = $this->fixture();
        $shift = $week->shifts()->sole();
        $shift->update(['end_time' => '14:00']);
        $worker = $this->worker(['operation' => 'publish', 'week_id' => $week->id, 'actor_id' => $admin->id, 'revision' => 1]);
        DB::beginTransaction();
        StaffProfile::whereKey($staff->id)->lockForUpdate()->firstOrFail();
        $this->releaseWorker($worker);
        $this->waitForRowLock($worker, 'staff_profiles');
        $appointment = app(AppointmentWorkflow::class)->autoBook(['customer_profile_id' => $customer->id], $service, Carbon::parse('2026-10-18 15:00:00', 'Asia/Manila'), $staff->id);
        DB::commit();
        $result = $this->workerResult($worker);
        $this->assertSame('conflicts', $result['status']);
        $this->assertContains($appointment->id, $result['ids']);
        $this->assertNull($week->fresh()->published_at);
        $this->assertSame(1, $week->fresh()->revision);
    }

    public function test_booking_waiting_for_publication_reloads_the_schedule_instead_of_using_cached_hours(): void
    {
        [$staff, $service, $customer, $week, $admin] = $this->fixture();
        $week->shifts()->sole()->update(['end_time' => '14:00']);
        $worker = $this->worker(['operation' => 'booking', 'week_id' => $week->id, 'staff_id' => $staff->id, 'service_id' => $service->id, 'customer_id' => $customer->id, 'starts_at' => '2026-10-18 15:00:00']);
        DB::beginTransaction();
        app(WeeklyRoster::class)->publish($week, $admin->id, 1);
        $this->releaseWorker($worker);
        $this->waitForRowLock($worker, 'staff_profiles');
        DB::commit();
        $result = $this->workerResult($worker);
        $this->assertTrue($result['hint_available']);
        $this->assertSame('validation', $result['status']);
        $this->assertSame(0, Appointment::count());
        $this->assertNotNull($week->fresh()->published_at);
    }

    private function fixture(): array
    {
        $staff = StaffProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $staff->services()->attach($service);
        $customer = CustomerProfile::factory()->create();
        $admin = User::factory()->admin()->create();
        StaffWeeklySchedule::factory()->for($staff)->create(['day_of_week' => 0, 'start_time' => '13:00', 'end_time' => '18:00', 'is_available' => true, 'ends_next_day' => false]);
        $week = app(WeeklyRoster::class)->startDraft('2026-10-18', 0);

        return [$staff, $service, $customer, $week, $admin];
    }

    private function worker(array $data): int
    {
        $directory = sys_get_temp_dir().'/casa-roster-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        file_put_contents($directory.'/input.json', json_encode([...$data, 'directory' => $directory, 'now' => '2026-10-18 10:00:00'], JSON_THROW_ON_ERROR));
        $process = new Process([PHP_BINARY, base_path('tests/Support/roster-worker.php'), $directory.'/input.json'], base_path(), timeout: 120);
        $process->start();
        $this->workers[] = [$process, $directory];
        $worker = count($this->workers) - 1;
        $deadline = microtime(true) + 30;
        while (! is_file($directory.'/ready.json') && microtime(true) < $deadline) {
            $this->assertWorkerRunning($worker, 'bootstrap');
            usleep(25000);
        }
        $this->assertFileExists($directory.'/ready.json', $this->workerDiagnostic($worker, 'bootstrap'));

        return $worker;
    }

    private function releaseWorker(int $worker): void
    {
        file_put_contents($this->workers[$worker][1].'/go', 'go');
    }

    private function waitForRowLock(int $worker, string $table): void
    {
        [$process, $directory] = $this->workers[$worker];
        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline) {
            if (is_file($directory.'/ready.json')) {
                $started = json_decode(file_get_contents($directory.'/ready.json'), true, flags: JSON_THROW_ON_ERROR);
                foreach (DB::select('SHOW FULL PROCESSLIST') as $row) {
                    $query = strtolower((string) $row->Info);
                    if ((int) $row->Id === (int) $started['connection_id'] && str_contains($query, $table) && str_contains($query, 'for update')) {
                        return;
                    }
                }
            }
            $this->assertWorkerRunning($worker, $table.' row lock');
            usleep(25000);
        }
        $this->fail($this->workerDiagnostic($worker, $table.' row lock'));
    }

    private function workerResult(int $worker): array
    {
        [$process, $directory] = $this->workers[$worker];
        $deadline = microtime(true) + 30;
        while ($process->isRunning() && microtime(true) < $deadline) {
            usleep(25000);
        }
        $this->assertFalse($process->isRunning(), $this->workerDiagnostic($worker, 'completion'));
        $process->wait();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertFileExists($directory.'/result.json');

        return json_decode(file_get_contents($directory.'/result.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertWorkerRunning(int $worker, string $phase): void
    {
        $this->assertTrue($this->workers[$worker][0]->isRunning(), $this->workerDiagnostic($worker, $phase));
    }

    private function workerDiagnostic(int $worker, string $expected): string
    {
        [$process, $directory] = $this->workers[$worker];
        $phase = is_file($directory.'/phase') ? trim(file_get_contents($directory.'/phase')) : 'not started';

        return 'Roster worker expected '.$expected.'; phase='.$phase.'; exit='.var_export($process->getExitCode(), true)
            .'; stderr='.substr($process->getErrorOutput(), -2000);
    }
}
