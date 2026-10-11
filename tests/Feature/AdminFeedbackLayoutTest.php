<?php

namespace Tests\Feature;

use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminFeedbackLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CustomerProfile $customer;

    private Service $service;

    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTimezone = date_default_timezone_get();
        Carbon::setTestNow('2026-10-07 16:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->customer = CustomerProfile::factory()->create();
        $this->service = Service::factory()->create(['name' => 'Signature massage']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->originalTimezone);

        parent::tearDown();
    }

    public function test_both_admin_roles_see_the_reference_layout_and_existing_detail_links(): void
    {
        $review = $this->review();

        foreach ([$this->admin, User::factory()->create(['role' => 'super_admin'])] as $user) {
            $response = $this->actingAs($user)->get(route('admin.feedback.index', absolute: false))
                ->assertOk()->assertSee('data-feedback-workspace', false)
                ->assertSeeInOrder(['Customer feedback and experience insights', 'Service sentiment summary', 'Experience topics', 'Customer reviews (1)'])
                ->assertSee('More filters')->assertSee('All dates')->assertSee('Treatment / Service')
                ->assertSee($this->customer->display_email)->assertSee('Scrollable customer reviews')
                ->assertSee('data-filter-toggle', false)->assertSee(route('admin.feedback.show', $review), false)
                ->assertSee('No topic findings')->assertDontSee('Clear filters');
            $this->assertSame(3, substr_count($response->getContent(), 'data-metric-card'));

            $this->get(route('admin.feedback.show', $review, false))->assertOk()
                ->assertSee('Rating')->assertSee('Source')->assertSee('Save reviewed classification');
        }
    }

    public function test_combined_filters_apply_to_reviews_sentiment_services_and_topic_findings(): void
    {
        $this->customer->user->update(['email' => 'feedback-match@example.com']);
        $match = $this->review();
        $this->topics($match, ['care_quality' => ['positive', 'negative'], 'scheduling_wait' => ['negative']]);
        $neutral = $this->review(['sentiment_label' => Feedback::SENTIMENT_NEUTRAL]);
        $otherService = $this->review(['service_id' => Service::factory()->create()->id]);
        $otherCustomer = $this->review(['customer_profile_id' => CustomerProfile::factory()->create()->id]);
        $old = $this->review(['submitted_at' => '2026-09-01 12:00:00']);
        foreach ([$neutral, $otherService, $otherCustomer, $old] as $review) {
            $this->topics($review, ['care_quality' => ['positive']]);
        }
        $filters = ['q' => 'feedback-match@', 'service_id' => $this->service->id, 'sentiment_label' => 'positive', 'topic' => 'care_quality', 'date_from' => '2026-10-07', 'date_to' => '2026-10-07'];

        $this->actingAs($this->admin)->get(route('admin.feedback.index', $filters, false))
            ->assertOk()->assertViewHas('feedback', fn ($rows) => $rows->getCollection()->modelKeys() === [$match->id])
            ->assertViewHas('summary', ['positive' => 1, 'neutral' => 0, 'negative' => 0])
            ->assertViewHas('serviceBreakdown', fn ($rows) => $rows->count() === 1 && (int) $rows->first()->total === 1 && (int) $rows->first()->positive === 1)
            ->assertViewHas('topicBreakdown', fn ($rows) => $rows->count() === 2 && (int) $rows->firstWhere('topic_key', 'care_quality')->total === 2)
            ->assertSee('1 of 2 positive (50%)')->assertSee('0 of 1 positive (0%)')
            ->assertSee('Scheduling and wait time')->assertSee('data-active-filters="6"', false)
            ->assertSee('<details class="feedback-more-filters"  open', false);
    }

    public function test_all_history_and_fixed_pagination_keep_aggregates_and_sort_order_across_pages(): void
    {
        $old = $this->review(['submitted_at' => '2025-01-01 12:00:00']);
        $newer = [];
        for ($index = 0; $index < 15; $index++) {
            $newer[] = $this->review();
        }
        $filters = ['q' => $this->customer->display_email, 'service_id' => $this->service->id, 'sentiment_label' => 'positive', 'per_page' => 100];

        foreach ([1 => array_reverse(array_map(fn ($row) => $row->id, $newer)), 2 => [$old->id]] as $page => $ids) {
            $this->actingAs($this->admin)->get(route('admin.feedback.index', $filters + ['page' => $page], false))
                ->assertOk()->assertViewHas('feedback', fn ($rows) => $rows->perPage() === 15 && $rows->total() === 16 && $rows->getCollection()->modelKeys() === $ids)
                ->assertViewHas('summary', ['positive' => 16, 'neutral' => 0, 'negative' => 0])
                ->assertViewHas('serviceBreakdown', fn ($rows) => (int) $rows->first()->total === 16)
                ->assertViewHas('dateFrom', '')->assertViewHas('dateTo', '')
                ->assertSee('service_id='.$this->service->id, false)->assertSee('sentiment_label=positive', false)
                ->assertSee('data-pagination-range', false)->assertDontSee('Rows per page');
        }
    }

    public function test_dates_allow_open_ended_ranges_and_use_inclusive_manila_days(): void
    {
        $before = $this->review(['submitted_at' => '2026-09-30 23:59:59']);
        $first = $this->review(['submitted_at' => '2026-10-01 00:00:00']);
        $last = $this->review(['submitted_at' => '2026-10-07 23:59:59']);
        $after = $this->review(['submitted_at' => '2026-10-08 00:00:00']);
        foreach ([
            [['date_from' => '2026-10-01', 'date_to' => '2026-10-07'], [$last->id, $first->id]],
            [['date_from' => '2026-10-01'], [$after->id, $last->id, $first->id]],
            [['date_to' => '2026-10-07'], [$last->id, $first->id, $before->id]],
        ] as [$filters, $ids]) {
            $this->actingAs($this->admin)->get(route('admin.feedback.index', $filters, false))
                ->assertOk()->assertViewHas('feedback', fn ($rows) => $rows->getCollection()->modelKeys() === $ids)
                ->assertViewHas('summary', ['positive' => count($ids), 'neutral' => 0, 'negative' => 0]);
        }

        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');
        foreach ([[$before, '2026-09-30 15:59:59'], [$first, '2026-09-30 16:00:00'], [$last, '2026-10-07 15:59:59'], [$after, '2026-10-07 16:00:00']] as [$review, $time]) {
            $review->forceFill(['submitted_at' => $time])->save();
        }
        $this->get(route('admin.feedback.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-07'], false))
            ->assertOk()->assertViewHas('feedback', fn ($rows) => $rows->getCollection()->modelKeys() === [$last->id, $first->id])
            ->assertSee('Oct 1, 2026')->assertSee('Oct 7, 2026');
    }

    public function test_service_summary_includes_all_services_and_sorts_by_total_then_name(): void
    {
        $this->review();
        $this->review(['sentiment_label' => 'negative']);
        foreach (['A massage', 'B massage', 'C massage', 'D massage', 'E massage', 'F massage'] as $name) {
            $this->review(['service_id' => Service::factory()->create(['name' => $name])->id, 'sentiment_label' => 'neutral']);
        }

        $this->actingAs($this->admin)->get(route('admin.feedback.index', absolute: false))
            ->assertOk()->assertViewHas('summary', ['positive' => 1, 'neutral' => 6, 'negative' => 1])
            ->assertViewHas('serviceBreakdown', fn ($rows) => $rows->count() === 7 && $rows->pluck('service_name')->all() === ['Signature massage', 'A massage', 'B massage', 'C massage', 'D massage', 'E massage', 'F massage'] && (int) $rows->first()->positive === 1 && (int) $rows->first()->negative === 1 && (int) $rows->first()->total === 2);
    }

    public function test_topic_percentages_use_findings_including_mixed_polarity_and_unclassified_reviews(): void
    {
        $review = $this->review(['sentiment_label' => 'neutral']);
        $this->topics($review, ['care_quality' => ['positive', 'negative'], 'therapist_service' => ['positive'], 'scheduling_wait' => ['negative']]);
        $this->review();

        $this->actingAs($this->admin)->get(route('admin.feedback.index', absolute: false))
            ->assertOk()->assertSee('1 of 2 positive (50%)')->assertSee('1 of 1 positive (100%)')
            ->assertSee('0 of 1 positive (0%)')->assertSee('Therapist service')
            ->assertSee('A review may have more than one finding.')
            ->assertViewHas('summary', ['positive' => 1, 'neutral' => 1, 'negative' => 0]);
    }

    public function test_search_supports_email_guest_contacts_service_and_comment_and_customer_sort(): void
    {
        $guest = CustomerProfile::factory()->create(['user_id' => null, 'guest_name' => 'Ada Guest', 'guest_phone' => '09171234567']);
        $guestReview = $this->review(['customer_profile_id' => $guest->id, 'comment' => 'Memorable treatment']);
        $this->customer->user->update(['name' => 'Zoe Customer', 'email' => 'zoe-feedback@example.com']);
        $enrolledReview = $this->review(['comment' => 'Unique customer comment']);

        foreach (['Ada Guest', '09171234567', $guest->customer_code, 'Memorable treatment'] as $q) {
            $this->actingAs($this->admin)->get(route('admin.feedback.index', ['q' => $q], false))
                ->assertOk()->assertViewHas('feedback', fn ($rows) => $rows->getCollection()->modelKeys() === [$guestReview->id])
                ->assertSee('09171234567');
        }
        foreach (['Zoe Customer', 'zoe-feedback@', 'Unique customer comment'] as $q) {
            $this->get(route('admin.feedback.index', ['q' => $q], false))
                ->assertOk()->assertViewHas('feedback', fn ($rows) => $rows->getCollection()->modelKeys() === [$enrolledReview->id]);
        }
        $this->get(route('admin.feedback.index', ['q' => 'Signature massage', 'sort' => 'customer', 'direction' => 'asc'], false))
            ->assertOk()->assertViewHas('feedback', fn ($rows) => $rows->getCollection()->modelKeys() === [$guestReview->id, $enrolledReview->id]);
        $this->get(route('admin.feedback.index', ['sort' => 'rating', 'direction' => 'desc'], false))->assertOk();
    }

    public function test_empty_states_missing_contacts_and_services_and_comments_are_safe(): void
    {
        $this->actingAs($this->admin)->get(route('admin.feedback.index', absolute: false))
            ->assertOk()->assertSee('No feedback yet')->assertSee('No topic findings')->assertDontSee('Clear filters')
            ->assertViewHas('summary', ['positive' => 0, 'neutral' => 0, 'negative' => 0]);
        $unknown = CustomerProfile::factory()->create(['user_id' => null, 'guest_name' => null, 'guest_phone' => null]);
        $review = $this->review(['customer_profile_id' => $unknown->id, 'service_id' => null, 'comment' => null]);
        $script = '<script>alert("feedback")</script>';
        $long = $this->review(['comment' => $script.str_repeat(' Long review', 30)]);

        $this->get(route('admin.feedback.index', absolute: false))->assertOk()
            ->assertSee('Customer unavailable')->assertSee('Unassigned service')->assertSee('No written comment.')
            ->assertSee($script)->assertDontSee($script, false)->assertSee('Read full review')
            ->assertSee(route('admin.feedback.show', $review), false)->assertSee(route('admin.feedback.show', $long), false)
            ->assertDontSee($long->comment);
        $this->get(route('admin.feedback.index', ['q' => 'no-such-review'], false))->assertOk()
            ->assertSee('No feedback found')->assertSee('Clear filters')->assertDontSee('data-pagination', false)
            ->assertViewHas('summary', ['positive' => 0, 'neutral' => 0, 'negative' => 0])
            ->assertViewHas('serviceBreakdown', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('topicBreakdown', fn ($rows) => $rows->isEmpty());
    }

    public function test_filters_validate_and_other_roles_are_forbidden(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.feedback.index', ['q' => ['bad'], 'service_id' => 999999, 'sentiment_label' => 'unknown', 'topic' => 'unknown', 'date_from' => '2026-13-01', 'page' => 0], false))
            ->assertUnprocessable()->assertJsonValidationErrors(['q', 'service_id', 'sentiment_label', 'topic', 'date_from', 'page']);
        $this->getJson(route('admin.feedback.index', ['date_from' => '2026-10-07', 'date_to' => '2026-10-06'], false))
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');
        foreach (['customer', 'staff', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.feedback.index', absolute: false))->assertForbidden();
        }
    }

    public function test_invalid_filter_forms_retain_values_and_recover_from_array_inputs(): void
    {
        $url = route('admin.feedback.index');
        $this->actingAs($this->admin)->from($url)
            ->get(route('admin.feedback.index', ['q' => 'Retained search', 'date_from' => '2026-10-07', 'date_to' => '2026-10-06'], false))
            ->assertRedirect($url)->assertSessionHasErrors('date_to')->assertSessionHasInput('q', 'Retained search');
        $dateError = session('errors')->first('date_to');
        $this->get($url)->assertOk()->assertSee('value="Retained search"', false)
            ->assertSee('value="2026-10-06"', false)->assertSee('x-init="filtersOpen = true"', false)
            ->assertSee($dateError);

        $this->from($url)->get(route('admin.feedback.index', ['q' => ['bad'], 'date_from' => ['bad']], false))
            ->assertRedirect($url)->assertSessionHasErrors(['q', 'date_from']);
        $this->get($url)->assertOk()->assertSee('The q field must be a string.')
            ->assertSee('x-init="filtersOpen = true"', false);
    }

    public function test_list_query_count_does_not_grow_with_review_rows(): void
    {
        $this->review();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.feedback.index', absolute: false))->assertOk();
        $oneRowQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($index = 0; $index < 14; $index++) {
            $this->review();
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.feedback.index', absolute: false))->assertOk();
        $this->assertLessThanOrEqual($oneRowQueries, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    private function review(array $attributes = []): Feedback
    {
        return Feedback::factory()->recycle([$this->customer, $this->service])->create(array_merge([
            'customer_profile_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'comment' => 'A relaxing treatment.',
        ], $attributes));
    }

    private function topics(Feedback $review, array $topics): void
    {
        foreach ($topics as $key => $polarities) {
            foreach ($polarities as $polarity) {
                $review->topics()->create(['topic_key' => $key, 'polarity' => $polarity]);
            }
        }
    }
}
