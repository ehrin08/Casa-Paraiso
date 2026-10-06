<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRewardsLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CustomerProfile $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-06 13:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->customer = CustomerProfile::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_roles_see_history_settings_summary_and_dedicated_editor(): void
    {
        $reward = $this->reward();

        foreach (['admin', 'super_admin'] as $role) {
            $user = $role === 'admin' ? $this->admin : User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('admin.promotions.index', absolute: false))
                ->assertOk()->assertSee('data-rewards-workspace', false)
                ->assertSee('Reward history')->assertSee('Issued date')->assertSee('Expiry')
                ->assertSee($this->customer->display_email)->assertSee('Sep 30, 2026')
                ->assertSee('Complimentary Hot Compress')->assertSee('All dates')
                ->assertSee('Scrollable customer reward history')->assertSee('data-filter-toggle', false)
                ->assertSee(route('admin.promotions.show', $reward), false)
                ->assertSee(route('admin.promotions.settings.edit'), false)
                ->assertSee('data-rewards-enabled="true"', false)
                ->assertDontSee('Save customer rewards')->assertDontSee('Dismiss this reward?');
            $this->assertSame(4, substr_count($response->getContent(), 'data-metric-card'));

            $this->get(route('admin.promotions.settings.edit', absolute: false))
                ->assertOk()->assertViewIs('admin.promotions.settings')
                ->assertSee('data-rewards-settings-editor', false)
                ->assertSee('Save customer rewards')->assertSee('Cancel')
                ->assertSee('groups[high_value][addon_code]', false)
                ->assertSee('groups[new_customer][is_active]', false)
                ->assertSee(route('admin.promotions.settings.update'), false);
        }
    }

    public function test_search_dates_and_lifecycle_filter_both_history_and_summary(): void
    {
        $this->customer->user->update(['email' => 'rewards-match@example.com']);
        $available = $this->reward();
        $reserved = $this->reward(['status' => PromotionSuggestion::STATUS_APPLIED]);
        Appointment::factory()->create(['customer_profile_id' => $this->customer->id, 'promotion_suggestion_id' => $reserved->id, 'status' => Appointment::STATUS_CONFIRMED]);
        $used = $this->reward(['status' => PromotionSuggestion::STATUS_APPLIED]);
        $expired = $this->reward(['expires_at' => now()]);
        $dismissed = $this->reward(['status' => PromotionSuggestion::STATUS_DISMISSED]);
        $this->reward(['created_at' => '2026-10-01 00:00:00']);
        $this->reward(['customer_profile_id' => CustomerProfile::factory()->create()->id]);
        $filters = ['q' => 'rewards-match@', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'];

        $this->actingAs($this->admin)->get(route('admin.promotions.index', $filters, false))
            ->assertOk()->assertViewHas('suggestions', fn ($rows) => $rows->total() === 5)
            ->assertViewHas('summary', ['available' => 1, 'reserved' => 1, 'used' => 1, 'expired' => 1])
            ->assertSee('data-active-filters="3"', false);

        foreach (['available' => $available, 'reserved' => $reserved, 'used' => $used, 'expired' => $expired, 'dismissed' => $dismissed] as $state => $reward) {
            $expected = array_fill_keys(['available', 'reserved', 'used', 'expired'], 0);
            if ($state !== 'dismissed') {
                $expected[$state] = 1;
            }
            $this->get(route('admin.promotions.index', $filters + ['lifecycle' => $state], false))
                ->assertOk()->assertViewHas('suggestions', fn ($rows) => $rows->getCollection()->modelKeys() === [$reward->id])
                ->assertViewHas('summary', $expected)->assertSee('rewards-status--'.$state, false);
        }

        foreach ([$this->customer->display_name, 'Complimentary Hot Compress', 'New customer'] as $search) {
            $this->get(route('admin.promotions.index', ['q' => $search], false))
                ->assertOk()->assertSee(route('admin.promotions.show', $available), false);
        }
    }

    public function test_issued_date_boundaries_and_open_ended_ranges_use_manila_days(): void
    {
        $before = $this->reward(['created_at' => '2026-08-31 23:59:59']);
        $first = $this->reward(['created_at' => '2026-09-01 00:00:00']);
        $last = $this->reward(['created_at' => '2026-09-30 23:59:59']);
        $after = $this->reward(['created_at' => '2026-10-01 00:00:00']);

        foreach ([
            [['date_from' => '2026-09-01', 'date_to' => '2026-09-30'], [$last->id, $first->id]],
            [['date_from' => '2026-09-01'], [$after->id, $last->id, $first->id]],
            [['date_to' => '2026-09-30'], [$last->id, $first->id, $before->id]],
            [[], [$after->id, $last->id, $first->id, $before->id]],
        ] as [$filters, $expected]) {
            $this->actingAs($this->admin)->get(route('admin.promotions.index', $filters, false))
                ->assertOk()->assertViewHas('suggestions', fn ($rows) => $rows->getCollection()->modelKeys() === $expected);
        }

        config(['app.timezone' => 'UTC']);
        $before->forceFill(['created_at' => '2026-08-31 15:59:59'])->save();
        $first->forceFill(['created_at' => '2026-08-31 16:00:00'])->save();
        $last->forceFill(['created_at' => '2026-09-30 15:59:59'])->save();
        $after->forceFill(['created_at' => '2026-09-30 16:00:00'])->save();
        $this->get(route('admin.promotions.index', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30'], false))
            ->assertOk()->assertViewHas('suggestions', fn ($rows) => $rows->getCollection()->modelKeys() === [$last->id, $first->id]);
    }

    public function test_fixed_pagination_keeps_filters_and_counts_every_matching_page(): void
    {
        for ($index = 0; $index < 16; $index++) {
            $this->reward();
        }
        $filters = ['q' => $this->customer->display_email, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'lifecycle' => 'available', 'per_page' => 100];

        foreach ([1, 2] as $page) {
            $this->actingAs($this->admin)->get(route('admin.promotions.index', $filters + ['page' => $page], false))
                ->assertOk()->assertViewHas('suggestions', fn ($rows) => $rows->perPage() === 15 && $rows->total() === 16 && $rows->count() === ($page === 1 ? 15 : 1))
                ->assertViewHas('summary', ['available' => 16, 'reserved' => 0, 'used' => 0, 'expired' => 0])
                ->assertSee('date_from=2026-09-01', false)->assertSee('date_to=2026-09-30', false)
                ->assertSee('lifecycle=available', false)->assertSee('data-pagination-range', false)
                ->assertDontSee('Rows per page');
        }
    }

    public function test_guest_contacts_historical_addons_and_escaped_fallback_offers(): void
    {
        $name = '<script>alert("reward")</script>'.str_repeat(' Long name', 10);
        $guest = CustomerProfile::factory()->create(['user_id' => null, 'guest_name' => $name, 'guest_phone' => '09171234567']);
        $this->reward(['customer_profile_id' => $guest->id]);
        Addon::query()->where('code', 'hot-compress')->update(['is_active' => false]);
        $offer = '<img src=x onerror=alert(1)> Previous reward';
        $this->reward(['rfm_segment_id' => null, 'addon_code' => 'unknown-old-addon', 'suggested_offer' => $offer, 'expires_at' => null]);
        $unknown = CustomerProfile::factory()->create(['user_id' => null, 'guest_name' => null, 'guest_phone' => null]);
        $this->reward(['customer_profile_id' => $unknown->id]);

        $this->actingAs($this->admin)->get(route('admin.promotions.index', absolute: false))
            ->assertOk()->assertSee($name)->assertDontSee($name, false)->assertSee('09171234567')
            ->assertSee('Complimentary Hot Compress')->assertSee($offer)->assertDontSee($offer, false)
            ->assertSee('No expiration')->assertSee('Customer unavailable');
        $this->get(route('admin.promotions.index', ['q' => '09171234567'], false))
            ->assertOk()->assertViewHas('suggestions', fn ($rows) => $rows->total() === 1);
    }

    public function test_history_batch_loads_addon_names_without_per_row_queries(): void
    {
        $this->reward();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.promotions.index', absolute: false))->assertOk();
        $oneRowQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        for ($index = 0; $index < 14; $index++) {
            $this->reward();
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.promotions.index', absolute: false))->assertOk();
        $this->assertLessThanOrEqual($oneRowQueries, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_settings_save_preserves_issued_rewards_and_other_company_settings(): void
    {
        ApplicationSetting::updateCurrent([...ApplicationSetting::defaults(), 'business_name' => 'Saved business', 'company_history' => 'Saved history', 'contact_phone' => '09179999999']);
        $reward = $this->reward();
        $before = $reward->only(['addon_code', 'expires_at']);

        foreach (['admin', 'super_admin'] as $role) {
            $user = $role === 'admin' ? $this->admin : User::factory()->create(['role' => $role]);
            $this->actingAs($user)->from(route('admin.promotions.settings.edit'))
                ->patch(route('admin.promotions.settings.update', absolute: false), [
                    'promotion_voucher_validity_days' => null,
                    'groups' => $this->rewardGroups('vip-room', false),
                ])->assertRedirect(route('admin.promotions.index'))->assertSessionHas('status', 'customer-rewards-updated');
        }

        $this->assertEquals($before, $reward->fresh()->only(['addon_code', 'expires_at']));
        $this->assertDatabaseHas('application_settings', ['business_name' => 'Saved business', 'company_history' => 'Saved history', 'contact_phone' => '09179999999', 'promotion_voucher_validity_days' => null]);
        $this->assertDatabaseHas('rfm_segments', ['preset_key' => 'loyal', 'addon_code' => 'vip-room', 'is_active' => false]);
        $this->get(route('admin.promotions.index', absolute: false))->assertOk()
            ->assertSee('data-rewards-enabled="false"', false)->assertSee('No groups enabled')->assertSee('No expiration');
    }

    public function test_settings_validation_returns_to_editor_with_values_and_errors(): void
    {
        $groups = $this->rewardGroups('vip-room', false);
        $groups['high_value']['addon_code'] = 'invalid-addon';
        $url = route('admin.promotions.settings.edit');

        $this->actingAs($this->admin)->from($url)
            ->patch(route('admin.promotions.settings.update', absolute: false), ['promotion_voucher_validity_days' => 60, 'groups' => $groups])
            ->assertRedirect($url)->assertSessionHasErrors('groups.high_value.addon_code')
            ->assertSessionHasInput('promotion_voucher_validity_days', 60)
            ->assertSessionHasInput('groups.loyal.is_active', 0);
        $message = session('errors')->first('groups.high_value.addon_code');
        $this->get($url)->assertOk()->assertSee('<option value="60" selected>', false)
            ->assertSee('<option value="vip-room" selected>', false)
            ->assertSee($message);

        $this->patchJson(route('admin.promotions.settings.update', absolute: false), ['promotion_voucher_validity_days' => 42, 'groups' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['promotion_voucher_validity_days', 'groups']);
    }

    public function test_dismissal_control_is_on_available_reward_detail_and_rejects_other_states(): void
    {
        $available = $this->reward();
        $expired = $this->reward(['expires_at' => now()]);
        $used = $this->reward(['status' => PromotionSuggestion::STATUS_APPLIED]);
        $reserved = $this->reward(['status' => PromotionSuggestion::STATUS_APPLIED]);
        Appointment::factory()->create(['customer_profile_id' => $this->customer->id, 'promotion_suggestion_id' => $reserved->id, 'status' => Appointment::STATUS_CONFIRMED]);
        $dismissed = $this->reward(['status' => PromotionSuggestion::STATUS_DISMISSED]);

        $this->actingAs($this->admin)->get(route('admin.promotions.show', $available))->assertOk()->assertSee('Dismiss this reward?');
        foreach ([$expired, $used, $reserved, $dismissed] as $reward) {
            $this->get(route('admin.promotions.show', $reward))->assertOk()->assertDontSee('Dismiss this reward?');
            $this->patchJson(route('admin.promotions.dismiss', $reward))->assertUnprocessable()->assertJsonValidationErrors('promotion');
        }
        $this->from(route('admin.promotions.show', $available))->patch(route('admin.promotions.dismiss', $available))
            ->assertRedirect(route('admin.promotions.show', $available));
        $this->assertSame(PromotionSuggestion::STATUS_DISMISSED, $available->fresh()->status);
    }

    public function test_empty_history_and_filtered_empty_states_are_distinct(): void
    {
        $this->actingAs($this->admin)->get(route('admin.promotions.index', absolute: false))
            ->assertOk()->assertSee('No customer rewards yet')->assertDontSee('Clear filters')->assertDontSee('data-pagination', false);
        $this->reward();
        $this->get(route('admin.promotions.index', ['q' => 'no-matching-reward'], false))->assertOk()
            ->assertSee('No customer rewards found')->assertSee('Clear filters')->assertDontSee('data-pagination', false)
            ->assertViewHas('summary', ['available' => 0, 'reserved' => 0, 'used' => 0, 'expired' => 0]);
    }

    public function test_filters_validate_and_other_roles_cannot_access_rewards_or_settings(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.promotions.index', ['q' => ['bad'], 'lifecycle' => 'invalid', 'date_from' => '2026-13-01', 'page' => 0], false))
            ->assertUnprocessable()->assertJsonValidationErrors(['q', 'lifecycle', 'date_from', 'page']);
        $this->getJson(route('admin.promotions.index', ['date_from' => '2026-10-06', 'date_to' => '2026-10-05'], false))
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');

        foreach (['customer', 'staff', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.promotions.index', absolute: false))->assertForbidden();
            $this->get(route('admin.promotions.settings.edit', absolute: false))->assertForbidden();
            $this->patch(route('admin.promotions.settings.update', absolute: false), [])->assertForbidden();
        }
    }

    private function reward(array $attributes = []): PromotionSuggestion
    {
        return PromotionSuggestion::factory()->create(array_merge([
            'customer_profile_id' => $this->customer->id,
            'rfm_segment_id' => RfmSegment::query()->where('preset_key', 'new_customer')->value('id'),
            'promotion_rule_id' => null,
            'created_at' => '2026-09-30 12:00:00',
        ], $attributes));
    }

    private function rewardGroups(string $addon, bool $active): array
    {
        return collect(config('casa.customer_rewards.presets'))->mapWithKeys(fn ($preset) => [
            $preset['key'] => ['addon_code' => $addon, 'is_active' => (int) $active],
        ])->all();
    }
}
