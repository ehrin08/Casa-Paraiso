<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffScheduleShift;
use App\Models\StaffScheduleWeek;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use App\Services\WeeklyRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeeklyRosterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $week = '2026-10-18';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-18 10:00:00', 'Asia/Manila'));
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_roster_reads_and_copy_review_never_create_or_replace_drafts(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $this->getJson(route('admin.staff-roster.show', ['week' => $this->week]))->assertOk()
            ->assertJsonPath('schedule_week_id', null)->assertJsonPath('revision', 0)->assertJsonCount(1, 'published_shifts');
        $this->get(route('admin.staff-roster.index', ['week' => $this->week]))->assertOk();
        $this->get(route('admin.staff-roster.copy.review', ['week' => $this->week]))->assertOk();
        $this->assertSame(0, StaffScheduleWeek::count());
        $week = $this->draft();
        $rows = $week->shifts()->pluck('id')->all();
        $this->get(route('admin.staff-roster.copy.review', ['week' => $this->week]))->assertOk();
        $this->assertSame($rows, $week->shifts()->pluck('id')->all());
        $this->assertSame(1, $week->fresh()->revision);
    }

    public function test_admin_can_copy_recurring_hours_and_publish_using_normal_forms(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $signature = app(WeeklyRoster::class)->copyReview($this->week)['source_signature'];
        $this->post(route('admin.staff-roster.copy'), ['week' => $this->week, 'expected_revision' => 0, 'source_signature' => $signature])
            ->assertRedirect(route('admin.staff-roster.index', ['week' => $this->week, 'view' => 'draft']));
        $week = StaffScheduleWeek::sole();
        $this->post(route('admin.staff-roster.publish', $week), ['expected_revision' => 1])->assertRedirect();
        $state = app(WeeklyRoster::class)->snapshot($this->week);
        $this->assertNotNull($week->fresh()->published_at);
        $this->assertTrue($state['has_draft']);
        $this->assertFalse($state['has_changes']);
        $this->assertSame(2, $state['revision']);
        $this->assertCount(1, $state['live_shifts']);
    }

    public function test_starting_a_filtered_draft_preserves_the_entire_effective_team(): void
    {
        $first = $this->therapist();
        $second = $this->therapist(['is_bookable' => false]);
        $this->recurring($first);
        $this->recurring($second, 1);
        $this->post(route('admin.staff-roster.draft.store'), [
            'week' => $this->week, 'expected_revision' => 0, 'return_filters' => ['staff_profile_id' => $first->id],
        ])->assertRedirect();
        $this->assertSame([$first->id, $second->id], StaffScheduleWeek::sole()->shifts()->orderBy('staff_profile_id')->pluck('staff_profile_id')->all());
        $this->assertFalse($second->fresh()->is_bookable);
    }

    public function test_drafts_do_not_change_live_hours_and_adjacent_shifts_are_allowed(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $week = $this->draft();
        $this->postJson(route('admin.staff-roster.shifts.store', $week), [...$this->shift($staff, '18:00', '19:00'), 'expected_revision' => 1])
            ->assertOk()->assertJsonPath('has_changes', true)->assertJsonCount(1, 'published_shifts');
        $this->postJson(route('admin.staff-roster.shifts.store', $week), [...$this->shift($staff, '17:30', '19:30'), 'expected_revision' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->assertSame(2, $week->fresh()->revision);
        $this->assertSame(2, $week->shifts()->count());
    }

    public function test_editing_a_shift_excludes_itself_and_rejects_wrong_week_or_published_rows(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $week = $this->draft();
        $shift = $week->shifts()->sole();
        $this->putJson(route('admin.staff-roster.shifts.update', [$week, $shift]), [...$this->shift($staff, '14:00', '00:00'), 'ends_next_day' => true, 'expected_revision' => 1])
            ->assertOk()->assertJsonPath('draft_shifts.0.ends_next_day', true);
        $other = StaffScheduleWeek::create(['week_start_date' => '2026-10-25', 'draft_initialized_at' => now()]);
        $this->deleteJson(route('admin.staff-roster.shifts.destroy', [$other, $shift]), ['expected_revision' => 0])->assertNotFound();
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 2])->assertOk();
        $published = $week->shifts()->where('version', StaffScheduleShift::VERSION_PUBLISHED)->sole();
        $this->putJson(route('admin.staff-roster.shifts.update', [$week, $published]), [...$this->shift($staff), 'expected_revision' => 3])->assertNotFound();
        $this->assertTrue($shift->fresh()->ends_next_day);
    }

    public function test_bulk_preview_is_read_only_and_replacement_is_limited_to_selected_cells(): void
    {
        $first = $this->therapist();
        $second = $this->therapist();
        $this->recurring($first);
        $this->recurring($first, 1);
        $this->recurring($second);
        $week = $this->draft();
        $data = ['expected_revision' => 1, 'mode' => 'hours', 'staff_ids' => [$first->id], 'dates' => [$this->week], 'start_time' => '15:00', 'end_time' => '20:00'];
        $untouchedIds = $week->shifts()->where(fn ($query) => $query->where('staff_profile_id', $second->id)
            ->orWhereDate('schedule_date', '2026-10-19'))->pluck('id');
        $this->post(route('admin.staff-roster.bulk.review', $week), $data)->assertOk()->assertSee('Review selected days');
        $this->assertSame(1, $week->fresh()->revision);
        $this->assertSame('13:00:00', $week->shifts()->where('staff_profile_id', $first->id)->whereDate('schedule_date', $this->week)->sole()->start_time);
        $this->post(route('admin.staff-roster.bulk.store', $week), $data)->assertRedirect();
        $rows = $week->shifts()->get();
        $this->assertCount(3, $rows);
        $this->assertSame($untouchedIds->all(), $rows->whereIn('id', $untouchedIds)->pluck('id')->all());
        $this->assertSame('15:00:00', $rows->first(fn ($row) => $row->staff_profile_id === $first->id && $row->schedule_date->toDateString() === $this->week)->start_time);
        $this->assertSame('13:00:00', $rows->where('staff_profile_id', $second->id)->first()->start_time);
        $this->assertSame('13:00:00', $rows->firstWhere('schedule_date', Carbon::parse('2026-10-19'))->start_time);
    }

    public function test_bulk_validation_rolls_back_every_selected_day(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $week = $this->draft();
        $this->postJson(route('admin.staff-roster.bulk.store', $week), [
            'expected_revision' => 1, 'mode' => 'hours', 'staff_ids' => [$staff->id], 'dates' => [$this->week, '2026-10-25'],
            'start_time' => '14:00', 'end_time' => '16:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('schedule_date');
        $this->assertSame(1, $week->fresh()->revision);
        $this->assertSame('13:00:00', $week->shifts()->sole()->start_time);
    }

    public function test_stale_shift_forms_recover_when_bulk_replacement_removed_the_original_row(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $week = $this->draft();
        $shift = $week->shifts()->sole();
        $this->postJson(route('admin.staff-roster.bulk.store', $week), [
            'expected_revision' => 1, 'mode' => 'hours', 'staff_ids' => [$staff->id],
            'dates' => [$this->week], 'start_time' => '15:00', 'end_time' => '20:00',
        ])->assertOk();
        $this->put(route('admin.staff-roster.shifts.update', [$week, $shift->id]), [
            ...$this->shift($staff, '14:00', '17:00'), 'expected_revision' => 1,
        ])->assertRedirect(route('admin.staff-roster.shifts.create', [$week, 'week' => $this->week, 'view' => 'draft']))
            ->assertSessionHasErrors('roster')->assertSessionHasInput('start_time', '14:00')->assertSessionHasInput('end_time', '17:00');
        $this->delete(route('admin.staff-roster.shifts.destroy', [$week, $shift->id]), ['expected_revision' => 1])
            ->assertRedirect()->assertSessionHasErrors('roster');
        $this->deleteJson(route('admin.staff-roster.shifts.destroy', [$week, $shift->id]), ['expected_revision' => 2])->assertNotFound();
        $this->assertSame(2, $week->fresh()->revision);
        $this->assertSame('15:00:00', $week->shifts()->sole()->start_time);
    }

    public function test_clearing_the_last_shift_retains_an_explicit_empty_draft_and_can_publish(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $week = $this->draft();
        $this->deleteJson(route('admin.staff-roster.shifts.destroy', [$week, $week->shifts()->sole()]), ['expected_revision' => 1])
            ->assertOk()->assertJsonPath('has_draft', true)->assertJsonCount(0, 'draft_shifts');
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 2])->assertOk()
            ->assertJsonPath('has_changes', false)->assertJsonCount(0, 'published_shifts');
        $this->assertNotNull($week->fresh()->published_at);
        $this->assertNotNull($week->fresh()->draft_initialized_at);
    }

    public function test_removed_therapist_bookings_warn_in_drafts_and_block_publication_without_losing_live_hours(): void
    {
        $first = $this->therapist();
        $second = $this->therapist();
        $this->recurring($first);
        $this->recurring($second);
        $week = $this->draft();
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 1])->assertOk();
        $appointment = $this->booking($second, '2026-10-18 15:00:00');
        $shift = $week->shifts()->where('version', 'draft')->where('staff_profile_id', $second->id)->sole();
        $this->deleteJson(route('admin.staff-roster.shifts.destroy', [$week, $shift]), ['expected_revision' => 2])->assertOk();
        $this->get(route('admin.staff-roster.index', ['week' => $this->week, 'staff_profile_id' => $first->id]))->assertOk()
            ->assertSee($appointment->appointment_number)->assertSee($second->user->name);
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 3])
            ->assertUnprocessable()->assertJsonPath('conflicts.0.id', $appointment->id)->assertJsonPath('conflicts.0.url', route('admin.appointments.show', $appointment));
        $this->assertSame(2, $week->shifts()->where('version', 'published')->count());
        $this->assertSame(1, $week->shifts()->where('version', 'draft')->count());
        $this->assertSame(3, $week->fresh()->revision);
    }

    public function test_shortened_shifts_and_repeating_future_weeks_are_checked_until_the_next_publication(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $week = $this->draft();
        $later = $this->booking($staff, '2026-11-01 15:00:00');
        $shift = $week->shifts()->sole();
        $this->putJson(route('admin.staff-roster.shifts.update', [$week, $shift]), [...$this->shift($staff, '13:00', '14:00'), 'expected_revision' => 1])->assertOk();
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 2])->assertUnprocessable()->assertJsonPath('conflicts.0.id', $later->id);
        $next = StaffScheduleWeek::create(['week_start_date' => '2026-10-25', 'published_at' => now()]);
        $next->shifts()->create([...$this->shift($staff), 'schedule_date' => '2026-10-25', 'version' => 'published']);
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 2])->assertOk();
        $this->assertSame(3, $week->fresh()->revision);
    }

    public function test_in_progress_bookings_remain_protected(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $week = $this->draft();
        $appointment = $this->booking($staff, '2026-10-18 14:30:00');
        $this->travelTo(Carbon::parse('2026-10-18 15:00:00', 'Asia/Manila'));
        $this->deleteJson(route('admin.staff-roster.shifts.destroy', [$week, $week->shifts()->sole()]), ['expected_revision' => 1])->assertOk();
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 2])
            ->assertUnprocessable()->assertJsonPath('conflicts.0.id', $appointment->id);
    }

    public function test_date_exceptions_apply_to_draft_coverage_and_empty_base_hours(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $week = $this->draft();
        $appointment = $this->booking($staff, '2026-10-18 15:00:00');
        StaffScheduleException::factory()->for($staff)->create(['exception_date' => $this->week, 'exception_type' => 'available', 'start_time' => '15:00', 'end_time' => '16:00']);
        $this->deleteJson(route('admin.staff-roster.shifts.destroy', [$week, $week->shifts()->sole()]), ['expected_revision' => 1])->assertOk();
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 2])->assertOk();
        StaffScheduleException::factory()->for($staff)->create(['exception_date' => $this->week, 'exception_type' => 'unavailable', 'start_time' => null, 'end_time' => null]);
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 3])
            ->assertUnprocessable()->assertJsonPath('conflicts.0.id', $appointment->id);
    }

    public function test_copy_and_publish_reject_invalid_source_ranges_and_cannot_discard_drafts(): void
    {
        $staff = $this->therapist();
        $legacy = $this->recurring($staff);
        $week = $this->draft();
        $signature = app(WeeklyRoster::class)->copyReview($this->week)['source_signature'];
        $legacy->update(['end_time' => '19:00']);
        $this->postJson(route('admin.staff-roster.copy'), ['week' => $this->week, 'expected_revision' => 1, 'source_signature' => $signature])
            ->assertUnprocessable()->assertJsonValidationErrors('roster');
        $this->assertSame('18:00:00', $week->shifts()->sole()->end_time);
        $week->shifts()->sole()->update(['start_time' => '13:15']);
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->assertNull($week->fresh()->published_at);
    }

    public function test_stale_revisions_and_past_weeks_are_rejected(): void
    {
        $staff = $this->therapist();
        $week = $this->draft();
        $this->postJson(route('admin.staff-roster.shifts.store', $week), [...$this->shift($staff), 'expected_revision' => 1])->assertOk();
        $this->postJson(route('admin.staff-roster.shifts.store', $week), [...$this->shift($staff, '19:00', '20:00'), 'expected_revision' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('roster');
        $this->postJson(route('admin.staff-roster.draft.store'), ['week' => '2026-10-11', 'expected_revision' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('week');
        $this->assertSame(1, StaffScheduleWeek::count());
        $this->assertSame(1, $week->shifts()->count());
    }

    public function test_copy_and_publication_do_not_silently_round_nonzero_stored_seconds(): void
    {
        $staff = $this->therapist();
        $legacy = $this->recurring($staff);
        $week = $this->draft();
        $signature = app(WeeklyRoster::class)->copyReview($this->week)['source_signature'];
        $legacy->update(['start_time' => '13:00:15']);
        $this->from(route('admin.staff-roster.copy.review', ['week' => $this->week]))
            ->get(route('admin.staff-roster.copy.review', ['week' => $this->week]))
            ->assertRedirect(route('admin.staff-roster.index', ['week' => $this->week, 'view' => 'draft']))
            ->assertSessionHasErrors('start_time');
        $this->postJson(route('admin.staff-roster.copy'), [
            'week' => $this->week, 'expected_revision' => 1, 'source_signature' => $signature,
        ])->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->assertSame('13:00:00', $week->shifts()->sole()->start_time);
        $week->shifts()->sole()->update(['end_time' => '18:00:15']);
        $this->from(route('admin.staff-roster.publish.review', $week))
            ->get(route('admin.staff-roster.publish.review', $week))
            ->assertRedirect(route('admin.staff-roster.index', ['week' => $this->week, 'view' => 'draft']))
            ->assertSessionHasErrors('start_time');
        $this->postJson(route('admin.staff-roster.publish', $week), ['expected_revision' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->assertNull($week->fresh()->published_at);
        $this->assertSame(1, $week->fresh()->revision);
        $this->assertSame('18:00:15', $week->shifts()->sole()->end_time);
    }

    public function test_time_bounds_midnight_flags_and_active_therapist_identity_are_validated(): void
    {
        $staff = $this->therapist();
        $week = $this->draft();
        foreach ([['12:30', '15:00', false], ['13:15', '15:00', false], ['14:00', '14:00', false], ['14:00', '15:00', true], ['14:00', '00:00', false]] as [$start, $end, $nextDay]) {
            $this->postJson(route('admin.staff-roster.shifts.store', $week), [...$this->shift($staff, $start, $end), 'ends_next_day' => $nextDay, 'expected_revision' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('start_time');
        }
        $staff->user->update(['is_active' => false]);
        $this->postJson(route('admin.staff-roster.shifts.store', $week), [...$this->shift($staff), 'expected_revision' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('staff_ids');
        $this->assertSame(0, $week->shifts()->count());
    }

    public function test_existing_published_hours_seed_a_draft_and_copy_uses_the_last_earlier_publication(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $prior = StaffScheduleWeek::create(['week_start_date' => '2026-10-11', 'published_at' => now()->subWeek()]);
        $prior->shifts()->create([...$this->shift($staff, '14:00', '00:00'), 'schedule_date' => '2026-10-11', 'version' => 'published']);
        $current = StaffScheduleWeek::create(['week_start_date' => $this->week, 'published_at' => now()]);
        $current->shifts()->create([...$this->shift($staff, '15:00', '18:00'), 'version' => 'published']);
        $week = $this->draft();
        $this->assertSame('15:00:00', $week->shifts()->where('version', 'draft')->sole()->start_time);
        $review = app(WeeklyRoster::class)->copyReview($this->week);
        $this->assertSame($prior->id, $review['copy_source']->id);
        $this->postJson(route('admin.staff-roster.copy'), ['week' => $this->week, 'expected_revision' => 1, 'source_signature' => $review['source_signature']])->assertOk();
        $draft = $week->shifts()->where('version', 'draft')->sole();
        $this->assertSame($this->week, $draft->schedule_date->toDateString());
        $this->assertSame('14:00:00', $draft->start_time);
        $this->assertTrue($draft->ends_next_day);
        $this->assertSame(1, $prior->shifts()->count());
    }

    public function test_copying_the_current_week_preserves_elapsed_days(): void
    {
        $staff = $this->therapist();
        $prior = StaffScheduleWeek::create(['week_start_date' => '2026-10-11', 'published_at' => now()->subWeek()]);
        $prior->shifts()->create([...$this->shift($staff, '14:00', '20:00'), 'schedule_date' => '2026-10-11', 'version' => 'published']);
        $prior->shifts()->create([...$this->shift($staff, '13:00', '16:00'), 'schedule_date' => '2026-10-14', 'version' => 'published']);
        $current = StaffScheduleWeek::create(['week_start_date' => $this->week, 'published_at' => now()]);
        $current->shifts()->create([...$this->shift($staff), 'version' => 'published']);
        $this->travelTo(Carbon::parse('2026-10-21 10:00:00', 'Asia/Manila'));
        $week = $this->draft();
        $review = app(WeeklyRoster::class)->copyReview($this->week);
        $this->postJson(route('admin.staff-roster.copy'), ['week' => $this->week, 'expected_revision' => 1, 'source_signature' => $review['source_signature']])->assertOk();
        $rows = $week->shifts()->where('version', 'draft')->get();
        $this->assertSame('13:00:00', $rows->first(fn ($row) => $row->schedule_date->toDateString() === $this->week)->start_time);
        $this->assertTrue($rows->contains(fn ($row) => $row->schedule_date->toDateString() === '2026-10-21' && $row->end_time === '16:00:00'));
    }

    public function test_draft_shift_removal_asks_for_confirmation_in_a_modal(): void
    {
        $staff = $this->therapist();
        $this->recurring($staff);
        $week = $this->draft();
        $shift = $week->shifts()->sole();

        $this->get(route('admin.staff-roster.index', ['week' => $this->week, 'view' => 'draft']))->assertOk()
            ->assertSee('Remove this shift?')
            ->assertSee('Keep shift')
            ->assertSee('casa-danger-button', false)
            ->assertSee(route('admin.staff-roster.shifts.destroy', [$week, $shift]), false)
            ->assertDontSee('<details class="schedules-remove">', false);
    }

    private function therapist(array $attributes = []): StaffProfile
    {
        return StaffProfile::factory()->create($attributes);
    }

    private function recurring(StaffProfile $staff, int $day = 0): StaffWeeklySchedule
    {
        return StaffWeeklySchedule::factory()->for($staff)->create(['day_of_week' => $day, 'start_time' => '13:00', 'end_time' => '18:00', 'ends_next_day' => false, 'is_available' => true]);
    }

    private function draft(): StaffScheduleWeek
    {
        $this->postJson(route('admin.staff-roster.draft.store'), ['week' => $this->week, 'expected_revision' => 0])->assertOk();

        return StaffScheduleWeek::query()->whereDate('week_start_date', $this->week)->sole();
    }

    private function shift(StaffProfile $staff, string $start = '13:00', string $end = '18:00'): array
    {
        return ['staff_profile_id' => $staff->id, 'schedule_date' => $this->week, 'start_time' => $start, 'end_time' => $end, 'ends_next_day' => $end === '00:00'];
    }

    private function booking(StaffProfile $staff, string $start): Appointment
    {
        $time = Carbon::parse($start, 'Asia/Manila');

        return Appointment::factory()->for($staff, 'staffProfile')->for(CustomerProfile::factory())->for(Service::factory()->create(['duration_minutes' => 60]))->create([
            'status' => Appointment::STATUS_CONFIRMED, 'scheduled_start_at' => $time, 'scheduled_end_at' => $time->copy()->addHour(), 'requested_start_at' => $time,
        ]);
    }
}
