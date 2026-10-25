<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffScheduleShift;
use App\Models\StaffScheduleWeek;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTherapistsLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_selection_defaults_to_the_first_result_and_links_preserve_the_current_list(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->therapist('Alpha Santos');
        $second = $this->therapist('Alpha Tan');
        $query = ['q' => 'Alpha', 'status' => 'active', 'bookable' => 'yes', 'sort' => 'name', 'direction' => 'asc', 'page' => 1];

        $this->actingAs($admin)->get(route('admin.staff.index', $query))->assertOk()
            ->assertViewHas('selectedStaffProfile', fn ($profile) => $profile->is($first))
            ->assertViewHas('selectedTab', 'profile')
            ->assertSee('data-selected-therapist="'.$first->id.'"', false);

        $response = $this->get(route('admin.staff.index', [...$query, 'selected' => $second->id, 'tab' => 'services']))
            ->assertOk()
            ->assertViewHas('selectedStaffProfile', fn ($profile) => $profile->is($second))
            ->assertViewHas('selectedTab', 'services')
            ->assertSee('data-selected-therapist="'.$second->id.'"', false);

        foreach ([$first, $second] as $profile) {
            $url = route('admin.staff.index', [...$query, 'selected' => $profile->id, 'tab' => 'services']).'#therapist-detail';
            $response->assertSee('href="'.e($url).'"', false);
        }
    }

    public function test_invalid_and_off_page_selections_fall_back_to_the_first_current_result(): void
    {
        $first = $this->therapist('A Therapist');
        for ($index = 1; $index <= 15; $index++) {
            $last = $this->therapist(sprintf('B Therapist %02d', $index));
        }
        $this->actingAs(User::factory()->admin()->create());

        foreach (['invalid', -1, 0, 999999, $last->id] as $selected) {
            $this->get(route('admin.staff.index', ['sort' => 'name', 'direction' => 'asc', 'selected' => $selected]))
                ->assertOk()
                ->assertViewHas('selectedStaffProfile', fn ($profile) => $profile->is($first));
        }
    }

    public function test_each_detail_section_and_invalid_tab_use_server_rendered_links(): void
    {
        $profile = $this->therapist('Section Therapist');
        $active = Service::factory()->create(['name' => 'Active massage']);
        $inactive = Service::factory()->create(['name' => 'Retired massage', 'is_active' => false]);
        $profile->services()->attach([$active->id, $inactive->id]);
        $this->actingAs(User::factory()->admin()->create());

        foreach (['profile', 'services', 'schedule', 'availability'] as $tab) {
            $response = $this->get(route('admin.staff.index', ['selected' => $profile->id, 'tab' => $tab]))
                ->assertOk()
                ->assertViewHas('selectedTab', $tab)
                ->assertSee('data-selected-tab="'.$tab.'"', false)
                ->assertSee('aria-label="Therapist detail sections"', false)
                ->assertSee('href="'.route('admin.staff.edit', $profile).'"', false)
                ->assertSee('href="'.route('admin.staff.show', $profile).'"', false);

            foreach (['profile', 'services', 'schedule', 'availability'] as $destination) {
                $url = route('admin.staff.index', ['selected' => $profile->id, 'tab' => $destination]).'#therapist-detail';
                $response->assertSee('href="'.e($url).'"', false);
            }

            if ($tab === 'services') {
                $response->assertSee('Active massage')->assertSee('Retired massage')->assertSee('Inactive')->assertSee('Edit services');
            } elseif ($tab === 'schedule') {
                $response->assertSee('href="'.route('admin.staff-roster.index', ['staff_profile_id' => $profile->id]).'"', false);
            } elseif ($tab === 'availability') {
                $response->assertSee('href="'.route('admin.staff.schedule-exceptions.create', $profile).'"', false);
            }
        }

        $this->get(route('admin.staff.index', ['selected' => $profile->id, 'tab' => 'invalid']))
            ->assertOk()->assertViewHas('selectedTab', 'profile')->assertSee('Contact information');
    }

    public function test_service_name_search_combines_with_account_and_booking_filters(): void
    {
        $service = Service::factory()->create(['name' => 'Unique reflexology']);
        $included = $this->therapist('Included Therapist');
        $inactive = $this->therapist('Inactive Therapist', [], ['is_active' => false]);
        $unbookable = $this->therapist('Unbookable Therapist', ['is_bookable' => false]);
        $unassigned = $this->therapist('Unassigned Therapist');
        foreach ([$included, $inactive, $unbookable] as $profile) {
            $profile->services()->attach($service);
        }

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.staff.index', [
            'q' => 'Unique reflexology', 'status' => 'active', 'bookable' => 'yes',
        ]))->assertOk()
            ->assertViewHas('staffProfiles', fn ($profiles) => $profiles->pluck('id')->all() === [$included->id])
            ->assertSee($included->user->name)
            ->assertDontSee($inactive->user->name)
            ->assertDontSee($unbookable->user->name)
            ->assertDontSee($unassigned->user->name);

        foreach (['Included Therapist', $included->user->email, '09171234567', 'Special Position', 'Special Expertise'] as $search) {
            $included->user->update(['phone' => '09171234567']);
            $included->update(['position' => 'Special Position', 'specialization' => 'Special Expertise']);
            $this->get(route('admin.staff.index', ['q' => $search]))->assertOk()
                ->assertViewHas('staffProfiles', fn ($profiles) => $profiles->pluck('id')->all() === [$included->id]);
        }
    }

    public function test_service_count_sorting_keeps_the_existing_sort_contract(): void
    {
        $fewer = $this->therapist('Zelda Therapist');
        $more = $this->therapist('Anna Therapist');
        $services = Service::factory()->count(3)->create();
        $fewer->services()->attach($services->first());
        $more->services()->attach($services);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.staff.index', ['sort' => 'services', 'direction' => 'desc']))
            ->assertOk()
            ->assertViewHas('staffProfiles', fn ($profiles) => $profiles->pluck('id')->all() === [$more->id, $fewer->id])
            ->assertSee('3 services')->assertSee('1 service');
    }

    public function test_empty_results_and_missing_profile_fields_have_clear_states(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.staff.index'))->assertOk()
            ->assertViewHas('selectedStaffProfile', null)->assertSee('No therapist profiles yet');

        $profile = $this->therapist('Minimal Therapist', ['position' => null, 'specialization' => null, 'bio' => null, 'hire_date' => null], ['phone' => null]);
        $this->get(route('admin.staff.index'))->assertOk()
            ->assertViewHas('selectedStaffProfile', fn ($selected) => $selected->is($profile))
            ->assertSee('Phone not set')->assertSee('No bio has been added yet.')
            ->assertSee('No services assigned yet.')->assertSee('No scheduled hours');

        $this->get(route('admin.staff.index', ['q' => 'Nobody matches']))->assertOk()
            ->assertViewHas('selectedStaffProfile', null)->assertSee('No therapists found')->assertSee('Clear filters');
    }

    public function test_profile_service_and_exception_text_are_escaped(): void
    {
        $profile = $this->therapist('<script>name()</script>', [
            'position' => '<script>position()</script>', 'bio' => '<script>bio()</script>',
            'specialization' => '<script>specialization()</script>',
        ]);
        $service = Service::factory()->create(['name' => '<script>service()</script>']);
        $profile->services()->attach($service);
        StaffScheduleException::factory()->for($profile)->create([
            'exception_date' => today()->addDay(), 'reason' => '<script>reason()</script>',
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('admin.staff.index'))->assertOk();
        foreach (['name', 'position', 'bio', 'specialization', 'service'] as $field) {
            $response->assertSee('&lt;script&gt;'.$field.'()&lt;/script&gt;', false)
                ->assertDontSee('<script>'.$field.'()</script>', false);
        }
        $this->get(route('admin.staff.index', ['tab' => 'availability']))->assertOk()
            ->assertSee('&lt;script&gt;reason()&lt;/script&gt;', false)->assertDontSee('<script>reason()</script>', false);
    }

    public function test_workspace_access_and_add_action_follow_existing_role_permissions(): void
    {
        $this->therapist('Visible Therapist');
        $this->get(route('admin.staff.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.staff.index'))->assertOk()
            ->assertDontSee('href="'.route('admin.staff.create').'"', false);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'email' => config('auth.super_admin_email')]);
        $this->actingAs($superAdmin)->get(route('admin.staff.index'))->assertOk()
            ->assertSee('href="'.route('admin.staff.create').'"', false)->assertSee('Add therapist');

        foreach ([User::ROLE_CUSTOMER, User::ROLE_STAFF, User::ROLE_RECEPTIONIST] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.staff.index'))->assertForbidden();
        }
    }

    public function test_recurring_summary_uses_manila_sunday_week_business_hours_and_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-02 14:00:00', 'Asia/Manila'));
        $profile = $this->therapist('Recurring Therapist');
        foreach ([StaffWeeklySchedule::MONDAY, StaffWeeklySchedule::FRIDAY] as $day) {
            StaffWeeklySchedule::factory()->for($profile)->create([
                'day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '00:00', 'ends_next_day' => true,
            ]);
        }
        StaffWeeklySchedule::factory()->for($profile)->create(['day_of_week' => StaffWeeklySchedule::TUESDAY, 'is_available' => false]);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.staff.index', ['tab' => 'schedule']))
            ->assertOk()->assertSee('1:00 PM')->assertSee('12:00 midnight')
            ->assertViewHas('scheduleWeekStart', fn ($date) => $date->toDateString() === '2026-09-27' && $date->timezoneName === 'Asia/Manila')
            ->assertViewHas('scheduleWeekEnd', fn ($date) => $date->toDateString() === '2026-10-03');
        $summary = $response->viewData('scheduleSummaries')[$profile->id];
        $this->assertSame('Mon, Fri', $summary['daysLabel']);
        $this->assertSame('1:00 PM – 12:00 midnight', $summary['hoursLabel']);
        $this->assertCount(7, $summary['days']);
        $this->assertSame(['start' => '1:00 PM', 'end' => '12:00 midnight'], $summary['days'][1]['windows'][0]);
        $this->assertSame([], $summary['days'][2]['windows']);
    }

    public function test_effective_summary_applies_past_week_exceptions_partial_blocks_and_extra_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-02 14:00:00', 'Asia/Manila'));
        $profile = $this->therapist('Exception Therapist');
        $admin = User::factory()->admin()->create();
        foreach ([StaffWeeklySchedule::MONDAY, StaffWeeklySchedule::FRIDAY] as $day) {
            StaffWeeklySchedule::factory()->for($profile)->create(['day_of_week' => $day, 'start_time' => '13:00', 'end_time' => '18:00']);
        }
        StaffScheduleException::factory()->for($profile)->for($admin, 'creator')->create(['exception_date' => '2026-09-28']);
        StaffScheduleException::factory()->for($profile)->for($admin, 'creator')->create([
            'exception_date' => '2026-10-02', 'start_time' => '15:00', 'end_time' => '16:00',
        ]);
        StaffScheduleException::factory()->for($profile)->for($admin, 'creator')->create([
            'exception_date' => '2026-09-29', 'exception_type' => StaffScheduleException::TYPE_AVAILABLE,
            'start_time' => '14:00', 'end_time' => '17:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();
        $summary = $response->viewData('scheduleSummaries')[$profile->id];
        $this->assertSame('Tue, Fri', $summary['daysLabel']);
        $this->assertSame('Hours vary by day', $summary['hoursLabel']);
        $this->assertSame([], $summary['days'][1]['windows']);
        $this->assertSame([
            ['start' => '1:00 PM', 'end' => '3:00 PM'],
            ['start' => '4:00 PM', 'end' => '6:00 PM'],
        ], $summary['days'][5]['windows']);
    }

    public function test_published_roster_takes_precedence_over_recurring_and_draft_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-02 14:00:00', 'Asia/Manila'));
        $profile = $this->therapist('Roster Therapist');
        StaffWeeklySchedule::factory()->for($profile)->create(['day_of_week' => StaffWeeklySchedule::FRIDAY]);
        $week = StaffScheduleWeek::query()->create(['week_start_date' => '2026-09-27', 'published_at' => now()]);
        StaffScheduleShift::query()->create([
            'staff_schedule_week_id' => $week->id, 'staff_profile_id' => $profile->id,
            'version' => StaffScheduleShift::VERSION_PUBLISHED, 'schedule_date' => '2026-09-29',
            'start_time' => '16:00', 'end_time' => '00:00', 'ends_next_day' => true,
        ]);
        StaffScheduleShift::query()->create([
            'staff_schedule_week_id' => $week->id, 'staff_profile_id' => $profile->id,
            'version' => StaffScheduleShift::VERSION_DRAFT, 'schedule_date' => '2026-09-27',
            'start_time' => '13:00', 'end_time' => '18:00',
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.staff.index'))->assertOk();
        $summary = $response->viewData('scheduleSummaries')[$profile->id];
        $this->assertSame('Tue', $summary['daysLabel']);
        $this->assertSame('4:00 PM – 12:00 midnight', $summary['hoursLabel']);
        $this->assertSame([], $summary['days'][0]['windows']);
        $this->assertSame([], $summary['days'][5]['windows']);
    }

    public function test_latest_earlier_published_roster_repeats_when_current_week_is_only_draft(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-02 14:00:00', 'Asia/Manila'));
        $profile = $this->therapist('Repeating Therapist');
        $week = StaffScheduleWeek::query()->create(['week_start_date' => '2026-09-20', 'published_at' => now()->subWeek()]);
        StaffScheduleShift::query()->create([
            'staff_schedule_week_id' => $week->id, 'staff_profile_id' => $profile->id,
            'version' => StaffScheduleShift::VERSION_PUBLISHED, 'schedule_date' => '2026-09-21',
            'start_time' => '15:00', 'end_time' => '19:00',
        ]);
        StaffScheduleWeek::query()->create(['week_start_date' => '2026-09-27']);
        StaffScheduleWeek::query()->create(['week_start_date' => '2026-10-04', 'published_at' => now()]);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.staff.index'))->assertOk();
        $summary = $response->viewData('scheduleSummaries')[$profile->id];
        $this->assertSame('Mon', $summary['daysLabel']);
        $this->assertSame('3:00 PM – 7:00 PM', $summary['hoursLabel']);
        $this->assertSame('2026-09-28', $summary['days'][1]['date']);
    }

    public function test_upcoming_exceptions_start_at_manila_today_and_include_loaded_creators(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 16:30:00', 'UTC'));
        $profile = $this->therapist('Availability Therapist', ['is_bookable' => true], ['is_active' => false]);
        $admin = User::factory()->admin()->create();
        StaffScheduleException::factory()->for($profile)->for($admin, 'creator')->create(['exception_date' => '2026-10-01', 'reason' => 'Past exception']);
        $today = StaffScheduleException::factory()->for($profile)->for($admin, 'creator')->create(['exception_date' => '2026-10-02', 'reason' => 'Today exception']);
        $future = StaffScheduleException::factory()->for($profile)->for($admin, 'creator')->create(['exception_date' => '2026-10-15', 'reason' => 'Future exception']);

        $this->actingAs($admin)->get(route('admin.staff.index', ['tab' => 'availability']))->assertOk()
            ->assertSee('Inactive account')->assertSee('Bookable')->assertDontSee('Past exception')
            ->assertSee('Today exception')->assertSee('Future exception')
            ->assertViewHas('upcomingExceptions', fn ($exceptions) => $exceptions->pluck('id')->all() === [$today->id, $future->id]
                && $exceptions->every(fn ($exception) => $exception->relationLoaded('creator')));
    }

    public function test_full_page_schedule_queries_are_bounded_and_catalog_is_not_loaded(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-02 14:00:00', 'Asia/Manila'));
        $admin = User::factory()->admin()->create();
        $week = StaffScheduleWeek::query()->create(['week_start_date' => '2026-09-27', 'published_at' => now()]);
        foreach (StaffProfile::factory()->count(15)->create() as $profile) {
            StaffWeeklySchedule::factory()->for($profile)->create();
            StaffScheduleShift::query()->create([
                'staff_schedule_week_id' => $week->id, 'staff_profile_id' => $profile->id,
                'version' => StaffScheduleShift::VERSION_PUBLISHED, 'schedule_date' => '2026-10-02',
                'start_time' => '13:00', 'end_time' => '18:00',
            ]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        foreach (['staff_weekly_schedules', 'staff_schedule_weeks', 'staff_schedule_shifts'] as $table) {
            $this->assertCount(1, $queries->filter(fn ($query) => str_contains($query, 'from `'.$table.'`')),
                $table.' should be fetched once for the entire result page.');
        }
        $this->assertCount(2, $queries->filter(fn ($query) => str_contains($query, 'from `staff_schedule_exceptions`')));
        $this->assertArrayNotHasKey('serviceCatalog', $response->original->getData());
        $this->assertArrayNotHasKey('activeServiceCount', $response->original->getData());
        $response->assertDontSee('service-catalog', false);
    }

    private function therapist(string $name, array $profileAttributes = [], array $userAttributes = []): StaffProfile
    {
        $user = User::factory()->staff()->create(['name' => $name, ...$userAttributes]);

        return StaffProfile::factory()->for($user)->create($profileAttributes);
    }
}
