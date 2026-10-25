<?php

use App\Exceptions\StaffScheduleConflictException;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleWeek;
use App\Services\AppointmentWorkflow;
use App\Services\WeeklyRoster;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => 'mariadb', 'DB_DATABASE' => 'testing', 'DB_URL' => ''] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::selectOne('select database() as name')->name !== 'testing' || ! str_contains(DB::selectOne('select version() as version')->version, 'MariaDB')) {
    throw new RuntimeException('Roster workers require isolated MariaDB testing.');
}
$data = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
Carbon::setTestNow(Carbon::parse($data['now'], 'Asia/Manila'));
$hint = null;
try {
    $roster = app(WeeklyRoster::class);
    if ($data['operation'] === 'booking') {
        $workflow = app(AppointmentWorkflow::class);
        $staff = StaffProfile::with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])->findOrFail($data['staff_id']);
        $service = Service::findOrFail($data['service_id']);
        $start = Carbon::parse($data['starts_at'], 'Asia/Manila');
        // Deliberately prime the resolver with the old live schedule before waiting for the publisher.
        $hint = $workflow->isStaffAvailable($staff, $service, $start);
    }
    file_put_contents($data['directory'].'/started.json', json_encode(['connection_id' => DB::selectOne('select connection_id() as id')->id, 'hint_available' => $hint], JSON_THROW_ON_ERROR));
    match ($data['operation']) {
        'publish' => $roster->publish(StaffScheduleWeek::findOrFail($data['week_id']), $data['actor_id'], $data['revision']),
        'shift' => $roster->saveShift(StaffScheduleWeek::findOrFail($data['week_id']), $data['shift'], $data['revision']),
        'bulk' => $roster->bulkReplace(StaffScheduleWeek::findOrFail($data['week_id']), $data['bulk'], $data['revision']),
        'booking' => $workflow->autoBook(['customer_profile_id' => $data['customer_id']], $service, $start, $data['staff_id']),
    };
    $result = ['status' => 'ok', 'hint_available' => $hint];
} catch (StaffScheduleConflictException $exception) {
    $result = ['status' => 'conflicts', 'ids' => array_column($exception->conflicts, 'id'), 'hint_available' => $hint];
} catch (ValidationException $exception) {
    $result = ['status' => 'validation', 'fields' => array_keys($exception->errors()), 'hint_available' => $hint];
} catch (Throwable $exception) {
    $result = ['status' => 'unexpected', 'exception' => get_class($exception), 'message' => $exception->getMessage()];
}
file_put_contents($data['directory'].'/result.json', json_encode($result, JSON_THROW_ON_ERROR));
