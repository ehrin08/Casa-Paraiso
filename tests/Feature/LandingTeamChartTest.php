<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingTeamChartTest extends TestCase
{
    use RefreshDatabase;

    private function publish(array $attributes, string $tier, int $order = 0): User
    {
        return User::factory()->create([
            ...$attributes,
            'show_on_org_chart' => true,
            'org_chart_tier' => $tier,
            'org_chart_order' => $order,
        ]);
    }

    public function test_published_staff_render_grouped_by_level_and_order_without_private_details(): void
    {
        $this->publish(['role' => User::ROLE_STAFF, 'name' => 'Bianca Team', 'email' => 'bianca@casaparaiso.test', 'phone' => '09170000001'], User::ORG_TIER_TEAM, 2);
        $this->publish(['role' => User::ROLE_STAFF, 'name' => 'Alma Team', 'public_title' => 'Senior Therapist'], User::ORG_TIER_TEAM, 1);
        $this->publish(['role' => User::ROLE_ADMIN, 'name' => 'Lea Leader', 'public_bio' => 'Keeps every day running smoothly.'], User::ORG_TIER_LEADERSHIP);
        $this->publish(['role' => User::ROLE_SUPER_ADMIN, 'name' => 'Omar Owner'], User::ORG_TIER_OWNER);

        $response = $this->get('/')->assertOk()
            ->assertSee('href="#about"', false)
            ->assertDontSee('href="#team"', false)
            ->assertSee('id="about"', false)
            ->assertSee('data-organization-chart', false)
            ->assertSeeInOrder(['Omar Owner', 'Lea Leader', 'Alma Team', 'Bianca Team'])
            ->assertSee('Senior Therapist')
            ->assertSee('Keeps every day running smoothly.')
            ->assertDontSee('bianca@casaparaiso.test')
            ->assertDontSee('09170000001');

        $html = $response->getContent();
        foreach (User::ORG_TIERS as $tier) {
            $this->assertSame(1, substr_count($html, 'data-org-tier="'.$tier.'"'));
        }
        // Without a public title the role label is shown.
        $this->assertStringContainsString('Therapist', $html);
        // The chart shows portraits only; each one opens its own details dialog.
        $this->assertSame(4, substr_count($html, 'data-org-member'));
        $this->assertSame(4, substr_count($html, 'data-org-dialog'));
        $this->assertSame(0, substr_count($html, 'class="landing-org-copy"'));
        $this->assertStringContainsString('aria-label="About Alma Team, Senior Therapist"', $html);
    }

    public function test_the_about_section_keeps_its_photo_when_no_team_is_published(): void
    {
        ApplicationSetting::factory()->create(['id' => 1, 'company_history' => 'Founded to help guests slow down.']);

        $this->get('/')->assertOk()
            ->assertSee('id="about"', false)
            ->assertSee('casa-towel-detail-1024.webp', false)
            ->assertDontSee('data-organization-chart', false);
    }

    public function test_hidden_inactive_customer_and_unplaced_accounts_never_appear(): void
    {
        User::factory()->staff()->create(['name' => 'Hidden Hana', 'show_on_org_chart' => false, 'org_chart_tier' => User::ORG_TIER_TEAM]);
        User::factory()->staff()->create(['name' => 'Inactive Ines', 'is_active' => false, 'show_on_org_chart' => true, 'org_chart_tier' => User::ORG_TIER_TEAM]);
        User::factory()->customer()->create(['name' => 'Customer Cleo', 'show_on_org_chart' => true, 'org_chart_tier' => User::ORG_TIER_TEAM]);
        User::factory()->staff()->create(['name' => 'Unplaced Uma', 'show_on_org_chart' => true, 'org_chart_tier' => null]);

        $this->get('/')->assertOk()
            ->assertDontSee('data-organization-chart', false)
            ->assertDontSee('href="#team"', false)
            ->assertDontSee('Hidden Hana')
            ->assertDontSee('Inactive Ines')
            ->assertDontSee('Customer Cleo')
            ->assertDontSee('Unplaced Uma');
    }

    public function test_deactivating_or_demoting_an_account_removes_it_from_the_chart(): void
    {
        $member = $this->publish(['role' => User::ROLE_STAFF, 'name' => 'Mila Member'], User::ORG_TIER_TEAM);
        $this->get('/')->assertSee('Mila Member');

        $member->update(['is_active' => false]);
        $this->get('/')->assertDontSee('Mila Member');

        $member->update(['is_active' => true, 'role' => User::ROLE_CUSTOMER]);
        $this->get('/')->assertDontSee('Mila Member');
    }

    public function test_uploaded_photos_are_shown_and_initials_are_the_fallback(): void
    {
        Storage::fake('public');
        $withPhoto = $this->publish(['role' => User::ROLE_STAFF, 'name' => 'Photo Pia'], User::ORG_TIER_TEAM);
        $this->publish(['role' => User::ROLE_RECEPTIONIST, 'name' => 'Nora Initials'], User::ORG_TIER_TEAM);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('admin.team.update', $withPhoto), [
            'photo' => UploadedFile::fake()->image('pia.png', 200, 200),
            'show_on_org_chart' => '1',
            'org_chart_tier' => User::ORG_TIER_TEAM,
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get('/')->assertOk()
            ->assertSee(Storage::disk('public')->url($withPhoto->fresh()->photo_path), false)
            ->assertSee('alt="Portrait of Photo Pia"', false)
            ->assertSee('>NI<', false);
    }
}
