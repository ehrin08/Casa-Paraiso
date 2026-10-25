<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\StaffScheduleWeek;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use App\Services\WeeklyRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TherapistScheduleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-18 10:00:00', 'Asia/Manila'));
    }

    public function test_all_scheduling_routes_enforce_admin_roles(): void
    {
        $staff = StaffProfile::factory()->create();
        $week = StaffScheduleWeek::create(['week_start_date' => '2026-10-18', 'draft_initialized_at' => now()]);
        $shift = $week->shifts()->create(['staff_profile_id' => $staff->id, 'version' => 'draft', 'schedule_date' => '2026-10-18', 'start_time' => '13:00', 'end_time' => '18:00']);
        $gets = [
            route('admin.staff-roster.index'), route('admin.staff-roster.show'), route('admin.staff-roster.copy.review'),
            route('admin.staff-roster.shifts.create', $week), route('admin.staff-roster.shifts.edit', [$week, $shift]),
            route('admin.staff-roster.bulk.edit', $week), route('admin.staff-roster.publish.review', $week),
        ];
        foreach ([User::factory()->customer()->create(), User::factory()->receptionist()->create(), $staff->user] as $user) {
            $this->actingAs($user);
            foreach ($gets as $url) {
                $this->get($url)->assertForbidden();
            }
            foreach ([
                route('admin.staff-roster.draft.store'), route('admin.staff-roster.copy'),
                route('admin.staff-roster.shifts.store', $week), route('admin.staff-roster.bulk.review', $week),
                route('admin.staff-roster.bulk.store', $week), route('admin.staff-roster.publish', $week),
            ] as $url) {
                $this->post($url, ['expected_revision' => 0])->assertForbidden();
            }
            $this->put(route('admin.staff-roster.shifts.update', [$week, $shift]), ['expected_revision' => 0])->assertForbidden();
            $this->delete(route('admin.staff-roster.shifts.destroy', [$week, $shift]), ['expected_revision' => 0])->assertForbidden();
        }
        foreach ([User::factory()->admin()->create(), User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'email' => config('auth.super_admin_email')])] as $user) {
            $this->actingAs($user)->get(route('admin.staff-roster.index'))->assertOk()->assertSee('Therapist schedules');
        }
    }

    public function test_team_page_is_paginated_and_schedule_queries_are_bounded(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (range(1, 16) as $number) {
            $person = StaffProfile::factory()->for(User::factory()->staff()->create(['name' => sprintf('Therapist %02d', $number)]))->create();
            StaffWeeklySchedule::factory()->for($person)->create(['day_of_week' => 0, 'start_time' => '13:00', 'end_time' => '00:00', 'ends_next_day' => true]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('admin.staff-roster.index'))->assertOk()->assertSee('Therapist 01')->assertDontSee('Therapist 16')
            ->assertSee('12:00 midnight')->assertSee('Sunday')->assertSee('Asia/Manila')
            ->assertSee('schedules-mobile', false)->assertSee('schedules-grid', false);
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/staff_schedule_weeks|staff_schedule_shifts|staff_schedule_exceptions|staff_weekly_schedules/', $query['query']));
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(6, $queries->count());
        $this->assertSame(15, $response->viewData('staffProfiles')->count());
        $this->get(route('admin.staff-roster.index', ['page' => 2]))->assertOk()->assertSee('Therapist 16')->assertDontSee('Therapist 01');
    }

    public function test_draft_modal_and_full_page_editor_share_normal_forms_and_escaped_context(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $staff = StaffProfile::factory()->for(User::factory()->staff()->create(['name' => '<script>Therapist</script>']))->create(['is_bookable' => false]);
        $week = app(WeeklyRoster::class)->startDraft('2026-10-18', 0);
        $url = route('admin.staff-roster.shifts.create', [$week, 'staff_profile_id' => $staff->id]);
        $this->get($url)->assertOk()->assertSee('data-workspace-role', false)->assertSee('name="expected_revision" value="1"', false)
            ->assertSee('method="POST"', false)->assertSee('Save draft shift')->assertSee('&lt;script&gt;Therapist&lt;/script&gt;', false);
        $this->get($url.'&modal=1')->assertOk()->assertSee('data-workspace-role', false);
        $this->get($url.'&modal=1', ['X-Casa-Panel' => '1'])->assertOk()->assertDontSee('data-workspace-role', false)
            ->assertSee('data-schedule-editor-content', false)->assertSee('Save draft shift')
            ->assertSee('data-turbo="false" data-schedule-submit', false);
        $this->get(route('admin.staff-roster.index', ['staff_profile_id' => $staff->id]))->assertOk()->assertSee('Booking disabled');
    }

    public function test_prefilling_an_add_shift_does_not_change_the_team_filter_on_return(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $staff = StaffProfile::factory()->create();
        $week = app(WeeklyRoster::class)->startDraft('2026-10-18', 0);
        $response = $this->get(route('admin.staff-roster.shifts.create', [$week, 'therapist' => $staff->id, 'schedule_date' => '2026-10-18']));
        $response->assertOk()->assertDontSee('name="return_filters[staff_profile_id]"', false);
        $this->post(route('admin.staff-roster.shifts.store', $week), [
            'staff_profile_id' => $staff->id, 'schedule_date' => '2026-10-18', 'start_time' => '13:00', 'end_time' => '18:00', 'expected_revision' => 1,
        ])->assertRedirect(route('admin.staff-roster.index', ['week' => '2026-10-18', 'view' => 'draft']));
    }

    public function test_bulk_review_and_confirmation_preserve_normal_form_navigation_and_save_status(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $staff = StaffProfile::factory()->create();
        $week = app(WeeklyRoster::class)->startDraft('2026-10-18', 0);
        $data = ['staff_ids' => [$staff->id], 'dates' => ['2026-10-18'], 'mode' => 'hours',
            'start_time' => '13:00', 'end_time' => '18:00', 'expected_revision' => 1];
        $this->post(route('admin.staff-roster.bulk.review', $week), $data)->assertOk()
            ->assertSee('data-turbo="false" data-schedule-submit', false)->assertSee('Save selected days to draft');
        $this->assertSame(0, $week->shifts()->count());
        $this->post(route('admin.staff-roster.bulk.store', $week), $data)
            ->assertRedirect(route('admin.staff-roster.index', ['week' => '2026-10-18', 'view' => 'draft']))
            ->assertSessionHas('roster_status', 'Selected days replaced in the draft.');
        $this->assertSame(1, $week->shifts()->count());
    }

    public function test_invalid_editor_submission_preserves_input_and_filter_context(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $staff = StaffProfile::factory()->create();
        StaffWeeklySchedule::factory()->for($staff)->create(['day_of_week' => 0, 'start_time' => '13:00', 'end_time' => '18:00']);
        $week = app(WeeklyRoster::class)->startDraft('2026-10-18', 0);
        $response = $this->post(route('admin.staff-roster.shifts.store', $week), [
            'staff_profile_id' => $staff->id, 'schedule_date' => '2026-10-18', 'start_time' => '14:00', 'end_time' => '17:00',
            'expected_revision' => 1, 'return_filters' => ['q' => 'Therapist', 'staff_profile_id' => $staff->id, 'page' => 1],
        ]);
        $response->assertSessionHasErrors('start_time')->assertSessionHasInput('start_time', '14:00')->assertSessionHasInput('end_time', '17:00');
        $this->assertStringContainsString('q=Therapist', $response->headers->get('Location'));
        $this->assertStringContainsString('/shifts/create', $response->headers->get('Location'));
        $this->assertSame(1, $week->fresh()->revision);
    }

    public function test_past_weeks_have_no_mutation_controls_and_invalid_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        StaffProfile::factory()->create();
        $this->get(route('admin.staff-roster.index', ['week' => '2026-10-11']))->assertOk()->assertSee('Past weeks are view-only.')
            ->assertDontSee('Start editing this week')->assertDontSee('Review and publish')->assertDontSee('Copy published week');
        $this->getJson(route('admin.staff-roster.index', ['week' => '2026-99-90']))->assertUnprocessable();
        $this->getJson(route('admin.staff-roster.index', ['view' => 'other']))->assertUnprocessable();
        $this->getJson(route('admin.staff-roster.index', ['page' => -1]))->assertUnprocessable();
        $this->assertSame(0, StaffScheduleWeek::count());
    }
}
