<?php

namespace Tests\Feature;

use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminAttendanceLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private StaffProfile $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->staff = StaffProfile::factory()->create();
        $this->travelTo(Carbon::parse('2026-10-03 16:05:00', 'UTC'));
    }

    public function test_daily_view_uses_manila_today_and_both_admin_roles_see_real_record_details(): void
    {
        $today = $this->attendance();
        $this->attendance('2026-10-03');
        $this->attendance('2026-10-05');
        $today->events()->create([
            'staff_profile_id' => $this->staff->id, 'event_type' => 'time_in', 'source' => 'auto_verified_qr',
            'occurred_at' => $today->time_in_at, 'recorded_by' => $this->staff->user->id,
        ]);

        foreach ([$this->admin, User::factory()->create(['role' => 'super_admin'])] as $user) {
            $response = $this->actingAs($user)->get(route('admin.attendance.index'))
                ->assertOk()->assertSee('data-attendance-workspace', false)
                ->assertSee('Sunday, October 4, 2026')->assertSee('Attendance station')
                ->assertSee('Selected record')->assertSee('Current saved record')->assertSee('Verified QR scan')
                ->assertSee($this->staff->user->email)->assertSee('On duty')->assertSee('1:00 PM')
                ->assertSee('data-selected-attendance="'.$today->id.'"', false)
                ->assertSee('data-filter-toggle', false)->assertSee('<noscript>', false)
                ->assertViewHas('filters', ['date' => '2026-10-04'])
                ->assertViewHas('records', fn ($rows) => $rows->total() === 1);
            $this->assertSame($today->id, $response->viewData('selectedRecord')->id);
        }
    }

    public function test_daily_all_dates_and_legacy_ranges_combine_with_therapist_and_status_filters(): void
    {
        $today = $this->attendance();
        $yesterday = $this->attendance('2026-10-03', ['time_out_at' => '2026-10-03 19:00:00']);
        $tomorrow = $this->attendance('2026-10-05');
        $other = $this->attendance(attributes: ['staff_profile_id' => StaffProfile::factory()->create()->id, 'time_out_at' => '2026-10-04 20:00:00']);
        $this->actingAs($this->admin);

        $this->get(route('admin.attendance.index', ['date' => '2026-10-03', 'staff_profile_id' => $this->staff->id, 'status' => 'closed']))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->modelKeys() === [$yesterday->id]);
        $this->get(route('admin.attendance.index', ['all_dates' => 1, 'date' => '2026-10-04']))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 4)
            ->assertViewHas('filters', ['all_dates' => 1]);
        $this->get(route('admin.attendance.index', ['date_from' => '2026-10-03', 'date_to' => '2026-10-04', 'staff_profile_id' => $this->staff->id]))
            ->assertOk()->assertSee('Existing range:')->assertViewHas('records', fn ($rows) => $rows->modelKeys() === [$today->id, $yesterday->id]);
        $this->get(route('admin.attendance.index', ['date_to' => '2026-10-03']))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->modelKeys() === [$yesterday->id]);
        $this->get(route('admin.attendance.index', ['date' => '2026-10-05', 'date_from' => '2026-10-03', 'date_to' => '2026-10-04']))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->modelKeys() === [$tomorrow->id]);
        $this->get(route('admin.attendance.index', ['date' => '2026-10-04', 'status' => 'closed']))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->modelKeys() === [$other->id]);
    }

    public function test_fixed_pagination_and_selection_are_limited_to_current_page(): void
    {
        $records = collect();
        for ($day = 0; $day < 16; $day++) {
            $records->push($this->attendance(CarbonImmutable::parse('2026-10-04')->subDays($day)->toDateString()));
        }
        $filters = ['all_dates' => 1, 'staff_profile_id' => $this->staff->id, 'status' => 'open'];
        $selected = $records[5];
        $response = $this->actingAs($this->admin)->get(route('admin.attendance.index', $filters + ['selected' => $selected->id, 'per_page' => 100]))
            ->assertOk()->assertViewHas('selectedRecord', fn ($row) => $row->id === $selected->id)
            ->assertViewHas('records', fn ($rows) => $rows->perPage() === 15 && $rows->count() === 15 && $rows->total() === 16);
        parse_str((string) parse_url($response->viewData('records')->url(2), PHP_URL_QUERY), $nextPage);
        $this->assertEquals($filters, array_intersect_key($nextPage, $filters));
        $this->assertArrayNotHasKey('selected', $nextPage);
        $this->assertArrayNotHasKey('per_page', $nextPage);
        foreach ([$records->last()->id, 999999, 'invalid', ['invalid']] as $selection) {
            $this->get(route('admin.attendance.index', $filters + ['selected' => $selection]))
                ->assertOk()->assertViewHas('selectedRecord', fn ($row) => $row->id === $records->first()->id);
        }
        $this->get(route('admin.attendance.index', $filters + ['page' => 2, 'selected' => $selected->id]))
            ->assertOk()->assertViewHas('selectedRecord', fn ($row) => $row->id === $records->last()->id);
        $this->get(route('admin.attendance.index', $filters + ['selected' => 0]))
            ->assertOk()->assertViewHas('selectedRecord', null)->assertDontSee('id="attendance-correction"', false);
        $tie = $this->attendance(attributes: ['staff_profile_id' => StaffProfile::factory()->create()->id]);
        $this->get(route('admin.attendance.index', ['all_dates' => 1]))->assertViewHas('records', fn ($rows) => $rows->first()->id === $tie->id);
    }

    public function test_empty_unrecorded_archived_and_escaped_audit_states_are_truthful(): void
    {
        $this->actingAs($this->admin)->get(route('admin.attendance.index'))
            ->assertOk()->assertSee('No attendance records')->assertSee('View all dates')->assertViewHas('selectedRecord', null);
        $record = $this->attendance(attributes: ['time_in_at' => null]);
        $this->get(route('admin.attendance.index'))->assertOk()->assertSee('Not clocked in')->assertSee('Unavailable')
            ->assertSee('No recorded events are available')->assertDontSee('System (Attendance station)');
        $this->get(route('admin.attendance.index', ['status' => 'open']))->assertViewHas('records', fn ($rows) => $rows->isEmpty());
        $this->staff->user->update(['name' => str_repeat('Long therapist name ', 8)]);
        $this->staff->delete();
        $record->events()->create([
            'staff_profile_id' => $this->staff->id, 'event_type' => 'time_in', 'source' => 'admin_correction',
            'occurred_at' => '2026-10-04 13:00:00', 'recorded_by' => $this->admin->id, 'reason' => '<script>alert("audit")</script>',
        ]);
        $this->get(route('admin.attendance.index'))->assertOk()->assertSee($this->staff->user->name)
            ->assertSee('(Archived)')->assertSee($this->admin->name)->assertSee('Administrator correction')
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("audit")</script>', false);
    }

    public function test_structured_correction_records_actor_and_retains_original_events_and_context(): void
    {
        $record = $this->attendance();
        $original = $record->events()->create([
            'staff_profile_id' => $this->staff->id, 'event_type' => 'time_in', 'source' => 'auto_verified_qr',
            'occurred_at' => $record->time_in_at, 'recorded_by' => $this->staff->user->id,
        ]);
        $filters = ['date' => '2026-10-04', 'staff_profile_id' => $this->staff->id, 'page' => 1];
        $response = $this->actingAs($this->admin)->patch(route('admin.attendance.correct', $record), [
            'action' => 'time_out', 'occurred_at' => '2026-10-04T19:12',
            'reason_code' => 'missed_time_out', 'details' => ' Forgot to scan. ', 'return_filters' => $filters,
        ])->assertSessionHas('status', 'attendance-corrected');
        $this->assertAttendanceRedirect($response, $filters + ['selected' => $record->id]);
        $this->assertSame('19:12', $record->fresh()->time_out_at->format('H:i'));
        $this->assertSame(2, $record->events()->count());
        $this->assertSame('auto_verified_qr', $original->fresh()->source);
        $event = $record->events()->latest('id')->first();
        $this->assertSame('Missed time-out: Forgot to scan.', $event->reason);
        $this->assertSame($this->admin->id, $event->recorded_by);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Completed')->assertSee($this->admin->name)->assertSee('Attendance activity (2)');
    }

    public function test_legacy_reason_and_timezone_aware_correction_remain_supported(): void
    {
        $record = $this->attendance('2026-09-30');
        $this->actingAs($this->admin)->patch(route('admin.attendance.correct', $record), [
            'action' => 'time_in', 'occurred_at' => '2026-09-30T05:15:00Z', 'reason' => ' Corrected from the reception log. ',
        ])->assertRedirect(route('admin.attendance.index', ['date' => '2026-09-30', 'page' => 1, 'selected' => $record->id]));
        $this->assertSame('13:15', $record->fresh()->time_in_at->format('H:i'));
        $this->assertSame('Corrected from the reception log.', $record->events()->first()->reason);
    }

    public function test_correction_clamps_empty_last_page_and_selects_another_matching_record(): void
    {
        $records = collect();
        for ($day = 0; $day < 16; $day++) {
            $records->push($this->attendance(CarbonImmutable::parse('2026-10-04')->subDays($day)->toDateString()));
        }
        $record = $records->last();
        $filters = ['all_dates' => 1, 'staff_profile_id' => $this->staff->id, 'status' => 'open', 'page' => 2];
        $response = $this->actingAs($this->admin)->patch(route('admin.attendance.correct', $record), [
            'action' => 'time_out', 'occurred_at' => $record->attendance_date->format('Y-m-d').'T19:00',
            'reason_code' => 'missed_time_out', 'return_filters' => $filters,
        ])->assertSessionHas('status', 'attendance-corrected');
        $filters['page'] = 1;
        $this->assertAttendanceRedirect($response, $filters + ['selected' => $record->id]);
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertViewHas('selectedRecord', fn ($row) => $row->id === $records->first()->id)
            ->assertViewHas('records', fn ($rows) => $rows->total() === 15);
    }

    public function test_invalid_correction_returns_to_open_panel_with_values_and_no_database_changes(): void
    {
        $record = $this->attendance();
        $filters = ['date' => '2026-10-04', 'staff_profile_id' => $this->staff->id, 'page' => 1];
        $response = $this->actingAs($this->admin)->patch(route('admin.attendance.correct', $record), [
            'action' => 'time_out', 'occurred_at' => '2026-10-04T12:00', 'reason_code' => 'other',
            'details' => 'Retain this detail', 'return_filters' => $filters, '_attendance_id' => $record->id,
        ])->assertSessionHasErrors('occurred_at');
        $this->assertAttendanceRedirect($response, $filters + ['selected' => $record->id], 'attendance-correction');
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('data-selected-attendance="'.$record->id.'"', false)->assertSee('value="2026-10-04T12:00"', false)
            ->assertSee('Retain this detail')->assertSee('Time out cannot be before time in.');
        $this->assertNull($record->fresh()->time_out_at);
        $this->assertSame(0, $record->events()->count());
    }

    public function test_invalid_reason_reopens_correct_record_and_cancel_does_not_reuse_failed_values(): void
    {
        $record = $this->attendance();
        $filters = ['date' => '2026-10-04', 'page' => 1];
        $response = $this->actingAs($this->admin)->patch(route('admin.attendance.correct', $record), [
            'action' => 'time_out', 'occurred_at' => '2026-10-04T19:00', 'reason_code' => 'other',
            'details' => '', 'return_filters' => $filters, '_attendance_id' => $record->id,
        ])->assertSessionHasErrors('details');
        $failedPage = $this->get($response->headers->get('Location'))->assertOk()->assertSee('value="2026-10-04T19:00"', false)
            ->assertSee('The correction could not be saved.')->assertSee('value="other" selected', false);
        preg_match('/<a href="([^"]+)" class="casa-button-secondary">Cancel<\/a>/', $failedPage->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $cancelUrl = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $this->assertNotSame(parse_url($response->headers->get('Location'), PHP_URL_QUERY), parse_url($cancelUrl, PHP_URL_QUERY));
        $resetPage = $this->get($cancelUrl)->assertOk()
            ->assertDontSee('value="2026-10-04T19:00"', false)->assertDontSee('The correction could not be saved.');
        preg_match('/<a href="([^"]+)" class="casa-button-secondary">Cancel<\/a>/', $resetPage->getContent(), $nextMatches);
        $this->assertNotSame($matches[1], $nextMatches[1]);
        $this->assertNull($record->fresh()->time_out_at);
    }

    public function test_malformed_old_record_identity_does_not_break_or_fill_another_record_panel(): void
    {
        $this->attendance();
        $this->actingAs($this->admin)->withSession(['_old_input' => [
            '_attendance_id' => ['invalid'], 'details' => 'Unrelated failed correction', 'occurred_at' => ['invalid'],
        ]])->get(route('admin.attendance.index'))->assertOk()->assertDontSee('Unrelated failed correction');
    }

    public function test_reason_and_context_validation_rejects_invalid_inputs_without_mutation(): void
    {
        $record = $this->attendance();
        $base = ['action' => 'time_out', 'occurred_at' => '2026-10-04T19:00', 'reason_code' => 'missed_time_out'];
        $invalid = [
            ['reason_code' => 'invalid'], ['reason_code' => null], ['reason_code' => 'other', 'details' => '   '],
            ['details' => str_repeat('x', 301)], ['reason' => ['invalid']], ['action' => 'invalid'],
            ['occurred_at' => 'invalid'], ['return_to' => 'https://example.com'],
            ['return_filters' => ['redirect' => 'https://example.com']],
            ['return_filters' => ['status' => 'invalid', 'page' => -1]],
            ['return_filters' => ['date_from' => '2026-10-05', 'date_to' => '2026-10-04']],
        ];
        foreach ($invalid as $payload) {
            $this->actingAs($this->admin)->patchJson(route('admin.attendance.correct', $record), array_replace($base, $payload))->assertUnprocessable();
            $this->assertNull($record->fresh()->time_out_at);
            $this->assertSame(0, $record->events()->count());
        }
        $this->patch(route('admin.attendance.correct', $record), $base + ['details' => str_repeat('x', 300)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Missed time-out: '.str_repeat('x', 300), $record->events()->first()->reason);
    }

    public function test_same_date_and_time_order_guards_rollback_all_invalid_corrections(): void
    {
        $record = $this->attendance(attributes: ['time_out_at' => '2026-10-04 19:00:00']);
        foreach ([['time_in', '2026-10-05T13:00'], ['time_in', '2026-10-04T20:00'], ['time_out', '2026-10-04T12:00']] as [$action, $time]) {
            $this->actingAs($this->admin)->patchJson(route('admin.attendance.correct', $record), [
                'action' => $action, 'occurred_at' => $time, 'reason_code' => 'incorrect_scan_time',
            ])->assertUnprocessable()->assertJsonValidationErrors('occurred_at');
            $this->assertSame('13:00', $record->fresh()->time_in_at->format('H:i'));
            $this->assertSame('19:00', $record->fresh()->time_out_at->format('H:i'));
        }
        $this->assertSame(0, $record->events()->count());
        $blank = $this->attendance('2026-10-03', ['time_in_at' => null]);
        $this->patchJson(route('admin.attendance.correct', $blank), [
            'action' => 'time_out', 'occurred_at' => '2026-10-03T19:00', 'reason' => 'Missing scan.',
        ])->assertUnprocessable()->assertJsonValidationErrors('occurred_at');
        $this->assertNull($blank->fresh()->time_out_at);
    }

    public function test_filter_validation_and_role_permissions_apply_to_list_and_corrections(): void
    {
        $record = $this->attendance();
        $this->actingAs($this->admin);
        foreach ([['date' => 'invalid'], ['all_dates' => 'yes'], ['status' => 'invalid'], ['staff_profile_id' => 999999], ['date_from' => '2026-10-05', 'date_to' => '2026-10-04']] as $filters) {
            $this->getJson(route('admin.attendance.index', $filters))->assertUnprocessable();
        }
        foreach ([$this->staff->user, User::factory()->receptionist()->create(), User::factory()->customer()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.attendance.index'))->assertForbidden();
            $this->patch(route('admin.attendance.correct', $record), [
                'action' => 'time_out', 'occurred_at' => '2026-10-04T19:00', 'reason_code' => 'missed_time_out',
            ])->assertForbidden();
        }
        $this->assertNull($record->fresh()->time_out_at);
    }

    private function attendance(string $date = '2026-10-04', array $attributes = []): StaffAttendance
    {
        return StaffAttendance::create(array_replace([
            'staff_profile_id' => $this->staff->id, 'attendance_date' => $date,
            'time_in_at' => $date.' 13:00:00', 'time_out_at' => null,
        ], $attributes));
    }

    private function assertAttendanceRedirect(TestResponse $response, array $query, ?string $fragment = null): void
    {
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertSame('/admin/attendance', parse_url($location, PHP_URL_PATH));
        parse_str((string) parse_url($location, PHP_URL_QUERY), $actualQuery);
        $this->assertEquals($query, $actualQuery);
        if ($fragment !== null) {
            $this->assertSame($fragment, parse_url($location, PHP_URL_FRAGMENT));
        }
    }
}
