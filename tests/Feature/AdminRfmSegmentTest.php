<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\CustomerProfile;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRfmSegmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_rules_page_lists_rules_in_priority_order_with_scoring_explanation(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.promotions.settings.edit'))
            ->assertOk()
            ->assertSee('Segment rules')->assertSee('How scoring works')
            ->assertSee('data-rfm-cutoffs', false)
            ->assertSee('R 4–5 · F 4–5 · M 4–5')
            ->assertSee(route('admin.promotions.segments.create'), false);

        $this->assertSame(
            ['High-value customer', 'Loyal customer', 'At-risk customer', 'Inactive customer', 'New customer'],
            $response->viewData('segments')->pluck('name')->all(),
        );
    }

    public function test_admin_can_create_and_edit_a_rule_that_is_placed_last(): void
    {
        $this->actingAs($this->admin)->get(route('admin.promotions.segments.create'))->assertOk()->assertSee('Score conditions');

        $this->post(route('admin.promotions.segments.store'), [
            'name' => 'Champions',
            'description' => 'Best on every measure.',
            'r_score_min' => 5, 'r_score_max' => 5,
            'f_score_min' => 5, 'f_score_max' => '',
            'm_score_min' => '', 'm_score_max' => '',
            'addon_code' => 'vip-room',
            'is_active' => 1,
        ])->assertRedirect(route('admin.promotions.settings.edit'))->assertSessionHas('status', 'rfm-segment-created');

        $segment = RfmSegment::query()->where('name', 'Champions')->firstOrFail();
        $this->assertSame(RfmSegment::query()->max('priority'), $segment->priority);
        $this->assertSame([5, 5, 5, null, null, null], [$segment->r_score_min, $segment->r_score_max, $segment->f_score_min, $segment->f_score_max, $segment->m_score_min, $segment->m_score_max]);
        $this->assertSame('R 5 · F 5 · M any', $segment->conditionSummary());

        $this->patch(route('admin.promotions.segments.update', $segment), [
            'name' => 'Champions',
            'm_score_min' => 4,
            'addon_code' => '',
            'is_active' => 0,
        ])->assertRedirect(route('admin.promotions.settings.edit'));

        $segment->refresh();
        $this->assertNull($segment->r_score_min);
        $this->assertSame(4, $segment->m_score_min);
        $this->assertNull($segment->addon_code);
        $this->assertFalse($segment->is_active);
    }

    public function test_rule_validation_rejects_bad_ranges_inactive_addons_and_duplicate_names(): void
    {
        Addon::query()->where('code', 'ventosa')->update(['is_active' => false]);

        $this->actingAs($this->admin)->from(route('admin.promotions.segments.create'))
            ->post(route('admin.promotions.segments.store'), [
                'name' => 'Loyal customer',
                'r_score_min' => 4, 'r_score_max' => 2,
                'f_score_min' => 6,
                'addon_code' => 'ventosa',
            ])
            ->assertRedirect(route('admin.promotions.segments.create'))
            ->assertSessionHasErrors(['name', 'r_score_max', 'f_score_min', 'addon_code']);

        $this->assertSame(5, RfmSegment::query()->count());
    }

    public function test_rules_can_be_moved_up_and_down(): void
    {
        $atRisk = RfmSegment::query()->where('preset_key', 'at_risk')->firstOrFail();

        $this->actingAs($this->admin)->patch(route('admin.promotions.segments.move', $atRisk), ['direction' => 'up'])
            ->assertRedirect(route('admin.promotions.settings.edit'));
        $this->assertSame(['high_value', 'at_risk', 'loyal', 'inactive', 'new_customer'], RfmSegment::query()->ordered()->pluck('preset_key')->all());

        $first = RfmSegment::query()->ordered()->firstOrFail();
        $this->patch(route('admin.promotions.segments.move', $first), ['direction' => 'up']);
        $this->patch(route('admin.promotions.segments.move', $atRisk), ['direction' => 'down']);
        $this->patch(route('admin.promotions.segments.move', $atRisk), ['direction' => 'down']);
        $this->assertSame(['high_value', 'loyal', 'inactive', 'at_risk', 'new_customer'], RfmSegment::query()->ordered()->pluck('preset_key')->all());

        $this->patch(route('admin.promotions.segments.move', $atRisk), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    }

    public function test_unused_rules_can_be_deleted_but_rules_with_rewards_are_kept(): void
    {
        $used = RfmSegment::query()->where('preset_key', 'loyal')->firstOrFail();
        PromotionSuggestion::factory()->create(['customer_profile_id' => CustomerProfile::factory(), 'rfm_segment_id' => $used->id]);
        $unused = RfmSegment::query()->where('preset_key', 'inactive')->firstOrFail();

        $this->actingAs($this->admin)->from(route('admin.promotions.settings.edit'))
            ->delete(route('admin.promotions.segments.destroy', $used))
            ->assertRedirect(route('admin.promotions.settings.edit'))->assertSessionHasErrors('segment');
        $this->assertModelExists($used);

        $this->delete(route('admin.promotions.segments.destroy', $unused))->assertSessionHas('status', 'rfm-segment-deleted');
        $this->assertModelMissing($unused);
    }

    public function test_only_admins_manage_rules(): void
    {
        $segment = RfmSegment::query()->firstOrFail();

        foreach (['receptionist', 'staff', 'customer'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.promotions.segments.create'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.promotions.segments.store'), ['name' => 'Nope'])->assertForbidden();
            $this->actingAs($user)->patch(route('admin.promotions.segments.move', $segment), ['direction' => 'down'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.promotions.segments.destroy', $segment))->assertForbidden();
        }

        $this->assertModelExists($segment);
    }
}
