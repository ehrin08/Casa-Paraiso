<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\RfmSegment;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRfmSegmentPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CustomerProfile $regular;

    private CustomerProfile $lapsed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        // With two customers, the regular scores 5 on every measure and the lapsed customer 1.
        $this->regular = $this->customerWithVisits(visits: 3, amount: 2000, daysAgo: 2);
        $this->lapsed = $this->customerWithVisits(visits: 1, amount: 500, daysAgo: 200);
    }

    public function test_new_group_preview_counts_customers_already_taken_by_every_active_group(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.promotions.segments.create'))
            ->assertOk()
            ->assertSee('data-rfm-preview', false)
            ->assertSee('Customers who fit right now:')
            ->assertSee('The others are already in a group higher in the list.');

        // High-value takes the regular and Inactive takes the lapsed customer, so a catch-all gets nobody.
        $this->assertSame(['fits' => 2, 'lands' => 0], $response->viewData('preview'));
        $this->assertSame([[5, 5, 5], [1, 1, 1]], $response->viewData('previewScores'));
        $this->assertCount(5, $response->viewData('previewRulesAbove'));
        $response->assertDontSee($this->regular->user->name)->assertDontSee($this->lapsed->customer_code);
    }

    public function test_editing_a_group_counts_only_the_groups_above_it(): void
    {
        $highValue = RfmSegment::query()->where('preset_key', 'high_value')->firstOrFail();
        $inactive = RfmSegment::query()->where('preset_key', 'inactive')->firstOrFail();

        $first = $this->actingAs($this->admin)->get(route('admin.promotions.segments.edit', $highValue))->assertOk();
        $this->assertSame(['fits' => 1, 'lands' => 1], $first->viewData('preview'));
        $this->assertSame([], $first->viewData('previewRulesAbove'));
        $first->assertDontSee('data-rfm-templates', false);

        $fourth = $this->get(route('admin.promotions.segments.edit', $inactive))->assertOk();
        $this->assertSame(['fits' => 1, 'lands' => 1], $fourth->viewData('preview'));
        $this->assertCount(3, $fourth->viewData('previewRulesAbove'));
    }

    public function test_switched_off_group_shows_that_nobody_would_join(): void
    {
        $inactive = RfmSegment::query()->where('preset_key', 'inactive')->firstOrFail();
        $inactive->update(['is_active' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.promotions.segments.edit', $inactive))->assertOk();

        $this->assertSame(['fits' => 1, 'lands' => 0], $response->viewData('preview'));
        $response->assertSee('Would join this group: none, because it is switched off.');
    }

    public function test_preview_follows_recovered_input_after_a_failed_save(): void
    {
        $this->actingAs($this->admin)->from(route('admin.promotions.segments.create'))
            ->post(route('admin.promotions.segments.store'), ['name' => '', 'r_score_max' => 1, 'is_active' => 0]);

        $response = $this->get(route('admin.promotions.segments.create'))->assertOk();

        $this->assertSame(['fits' => 1, 'lands' => 0], $response->viewData('preview'));
    }

    public function test_templates_fill_the_new_group_form_without_saving(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.promotions.segments.create'))
            ->assertOk()
            ->assertSee('Start from a template')
            ->assertSee(route('admin.promotions.segments.create', ['template' => 'win-back']), false)
            ->assertSee('Start blank');
        $this->assertNull($response->viewData('segment')->r_score_min);

        $winBack = $this->get(route('admin.promotions.segments.create', ['template' => 'win-back']))
            ->assertOk()
            ->assertSee('value="Win back lost customers"', false);
        $segment = $winBack->viewData('segment');
        $this->assertFalse($segment->exists);
        $this->assertSame([1, 2, 3, 5, null, null], [$segment->r_score_min, $segment->r_score_max, $segment->f_score_min, $segment->f_score_max, $segment->m_score_min, $segment->m_score_max]);
        $this->assertSame(5, RfmSegment::query()->count());

        $loyal = $this->get(route('admin.promotions.segments.create', ['template' => 'loyal-regulars']));
        // The regular fits, but High-value is checked first.
        $this->assertSame(['fits' => 1, 'lands' => 0], $loyal->viewData('preview'));

        $unknown = $this->get(route('admin.promotions.segments.create', ['template' => 'nope']))->assertOk();
        $this->assertNull($unknown->viewData('segment')->name);
    }

    private function customerWithVisits(int $visits, float $amount, int $daysAgo): CustomerProfile
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        foreach (range(1, $visits) as $visit) {
            $appointment = Appointment::factory()->for($customer)->for($service)->create(['status' => Appointment::STATUS_COMPLETED]);
            Transaction::factory()->for($customer)->for($appointment)->for($service)->create([
                'amount' => $amount,
                'payment_status' => Transaction::PAYMENT_PAID,
                'paid_at' => now()->subDays($daysAgo + $visit - 1),
            ]);
        }

        return $customer;
    }
}
