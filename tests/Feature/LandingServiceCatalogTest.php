<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\ApplicationSetting;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_describes_immediately_confirmed_bookings(): void
    {
        Service::factory()->create(['is_active' => true]);

        $response = $this->get('/')
            ->assertOk()
            ->assertSee('<title>Casa Paraiso Body and Wellness Spa</title>', false)
            ->assertSee('Book an appointment')
            ->assertSee('>Log in</a>', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('A successful booking reserves and confirms your appointment immediately.')
            ->assertSee('Log in, choose a treatment, and pick an available time.')
            ->assertSee('data-landing-catalog-cta', false)
            ->assertDontSee('Request an appointment')
            ->assertDontSee('Request-first care');

        $xpath = $this->landingXPath($response->getContent());

        $this->assertSame(3, $xpath->query('//a[@href="'.route('login').'"]')->length);
        $this->assertSame(1, $xpath->query('//p[@class="landing-login-note"]')->length);
        $this->assertSame(1, $xpath->query('//a[@data-landing-catalog-cta]')->length);
        $this->assertSame(route('login'), $xpath->query('//a[@data-landing-catalog-cta]')->item(0)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//section[@id="visit"]//a[@href="'.route('login').'"] | //footer//a[@href="'.route('login').'"]')->length);
    }

    public function test_landing_page_shows_configured_packages_as_a_contact_only_fallback_when_no_services_are_active(): void
    {
        Service::factory()->create(['is_active' => false]);

        $response = $this->get('/')
            ->assertOk()
            ->assertSee('data-landing-catalog-fallback', false)
            ->assertSee('GAIA TOUCH')
            ->assertSee('TETHYS FLOW')
            ->assertSee('HESTIA WARMTH')
            ->assertSee('AURORA BREEZE')
            ->assertSee('PHP 499.00')
            ->assertSee('PHP 649.00')
            ->assertSee('PHP 749.00')
            ->assertSee('PHP 849.00')
            ->assertSee('These signature packages are shown for reference.')
            ->assertSee('Book an appointment')
            ->assertSee('>Log in</a>', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('>Contact for availability</a>', false)
            ->assertSee('Reference packages from')
            ->assertSee('Ask about availability')
            ->assertDontSee('data-landing-catalog-cta', false)
            ->assertDontSee('>Book appointment ', false)
            ->assertDontSee('Instant confirmation');

        $xpath = $this->landingXPath($response->getContent());
        $catalogLinks = $xpath->query('//section[@id="treatments"]//a');

        $this->assertSame(1, $catalogLinks->length);
        $this->assertSame('#visit', $catalogLinks->item(0)->getAttribute('href'));
        $this->assertSame('Ask about availability', trim($catalogLinks->item(0)->textContent));
    }

    public function test_authenticated_visitors_see_their_workspace_action_without_guest_ctas(): void
    {
        Service::factory()->create(['is_active' => true]);

        foreach ([User::ROLE_CUSTOMER, User::ROLE_STAFF, User::ROLE_RECEPTIONIST, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)
                ->get('/')
                ->assertOk()
                ->assertSee('Open workspace')
                ->assertDontSee('>Log in</a>', false)
                ->assertDontSee('>Book an appointment</a>', false)
                ->assertDontSee('Log in to book your visit.');

            $xpath = $this->landingXPath($response->getContent());
            $workspaceLinks = $xpath->query('//a[normalize-space()="Open workspace"]');

            $this->assertSame(3, $workspaceLinks->length);

            foreach ($workspaceLinks as $link) {
                $this->assertSame(route($user->homeRouteName()), $link->getAttribute('href'));
            }
        }
    }

    public function test_landing_page_uses_current_active_service_prices_after_an_admin_update(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create([
            'name' => 'Live Price Massage',
            'description' => 'A current public treatment.',
            'duration_minutes' => 90,
            'price' => 499,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.services.update', $service, false), [
                'name' => 'Live Price Massage',
                'description' => 'A current public treatment.',
                'duration_minutes' => 90,
                'price' => 1250,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.services.show', $service, false));

        $this->get('/')
            ->assertOk()
            ->assertSee('Live Price Massage')
            ->assertSee('A current public treatment.')
            ->assertSee('1 Hour 30 Minutes')
            ->assertSee('PHP 1,250.00');
    }

    public function test_landing_page_shows_only_active_services_and_uses_the_lowest_active_price(): void
    {
        Service::factory()->create(['name' => 'Premium Treatment', 'price' => 1800, 'is_active' => true]);
        Service::factory()->create(['name' => 'Starter Treatment', 'price' => 750, 'is_active' => true]);
        Service::factory()->create(['name' => 'Hidden Treatment', 'price' => 300, 'is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Premium Treatment')
            ->assertSee('Starter Treatment')
            ->assertDontSee('Hidden Treatment')
            ->assertDontSee('GAIA TOUCH')
            ->assertDontSee('data-landing-catalog-fallback', false)
            ->assertSee('PHP 750.00');
    }

    public function test_all_active_treatments_and_safe_custom_copy_are_rendered_without_a_three_card_limit(): void
    {
        $services = collect(range(1, 5))->map(fn (int $number) => Service::factory()->create([
            'name' => 'Custom Treatment '.$number,
            'slug' => 'custom-treatment-'.$number,
            'description' => $number === 5 ? '<script>unsafe()</script> '.str_repeat('Restorative care. ', 40) : 'Published treatment details.',
            'duration_minutes' => 75,
            'price' => 550 + $number,
            'is_active' => true,
        ]));

        $response = $this->get('/')->assertOk();

        foreach ($services as $service) {
            $response->assertSee($service->name);
        }

        $response->assertSee('1 Hour 15 Minutes')
            ->assertSee('images/spa/casa-towel-detail-720.webp')
            ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)
            ->assertDontSee('<script>unsafe()</script>', false);

        $this->assertSame(5, substr_count($response->getContent(), 'data-landing-treatment'));

        $xpath = $this->landingXPath($response->getContent());

        $this->assertSame(0, $xpath->query('//article[@data-landing-treatment]//a')->length);
        $this->assertSame(1, $xpath->query('//section[@id="treatments"]//a')->length);
    }

    public function test_optional_additions_show_only_active_saved_entries_and_safe_custom_thumbnails(): void
    {
        Addon::query()->update(['is_active' => false]);
        Addon::query()->updateOrCreate(['code' => 'hot-stone'], ['name' => 'Hot Stone', 'price' => 210, 'duration_minutes' => 0, 'is_active' => true]);
        Addon::query()->create(['code' => 'custom-addition', 'name' => 'Custom Addition', 'price' => 125, 'duration_minutes' => 15, 'is_active' => true]);
        Addon::query()->create(['code' => 'hidden-addition', 'name' => 'Hidden Addition', 'price' => 100, 'duration_minutes' => 0, 'is_active' => false]);

        $response = $this->get('/')->assertOk()
            ->assertSee('A little extra comfort.')
            ->assertSee('Hot Stone')
            ->assertSee('PHP 210.00')
            ->assertSee('Custom Addition')
            ->assertSee('+15 minutes')
            ->assertSee('images/spa/casa-hot-stone-720.webp')
            ->assertSee('images/spa/casa-towel-detail-720.webp')
            ->assertDontSee('Hidden Addition');

        $this->assertLessThan(strpos($response->getContent(), 'id="visit"'), strpos($response->getContent(), 'data-landing-section="addons"'));
    }

    public function test_public_chart_preserves_missing_tiers_and_every_published_team_entry(): void
    {
        $settings = ApplicationSetting::factory()->create(['id' => 1]);
        $team = array_map(fn (int $number) => ['title' => 'Published role '.$number, 'name' => 'Team Member '.$number], range(1, 8));

        foreach ([
            ['owner' => ['title' => 'Owner', 'name' => 'Published Owner']],
            ['admin' => ['title' => 'Administrator', 'name' => 'Published Administrator']],
            ['team' => $team],
        ] as $chart) {
            $settings->update(['company_history' => null, 'organization_chart' => $chart]);
            $response = $this->get('/')->assertOk()->assertSee('data-organization-chart', false);
            $html = $response->getContent();
            $this->assertSame(isset($chart['owner']) ? 1 : 0, substr_count($html, 'data-org-tier="owner"'));
            $this->assertSame(isset($chart['admin']) ? 1 : 0, substr_count($html, 'data-org-tier="admin"'));
            $this->assertSame(count($chart['team'] ?? []), substr_count($html, 'data-org-tier="team"'));
            foreach ($chart['team'] ?? [] as $node) {
                $response->assertSee($node['name']);
            }
        }
    }

    public function test_unpublished_profile_and_missing_contact_links_are_omitted(): void
    {
        Addon::query()->update(['is_active' => false]);
        ApplicationSetting::factory()->create([
            'id' => 1, 'company_history' => null, 'organization_chart' => null,
            'business_address' => '', 'location_landmarks' => null,
            'messenger_url' => null, 'facebook_url' => null, 'map_url' => null,
        ]);

        $this->get('/')->assertOk()
            ->assertDontSee('href="#about"', false)
            ->assertDontSee('data-organization-chart', false)
            ->assertDontSee('data-landing-section="addons"', false)
            ->assertDontSee('Message us')
            ->assertDontSee('Visit Facebook')
            ->assertDontSee('Get directions')
            ->assertDontSee('class="landing-contact-actions"', false)
            ->assertSee('We look forward to welcoming you.');
    }

    private function landingXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }
}
