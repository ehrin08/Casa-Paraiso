<?php

namespace Tests\Feature;

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

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Casa Paraiso Body and Wellness Spa</title>', false)
            ->assertSee('Book an appointment')
            ->assertSee('Instant confirmation')
            ->assertSee('A successful booking immediately reserves your appointment.')
            ->assertSee('data-landing-catalog-cta', false)
            ->assertDontSee('Request an appointment')
            ->assertDontSee('Request-first care');
    }

    public function test_landing_page_shows_configured_packages_as_a_contact_only_fallback_when_no_services_are_active(): void
    {
        Service::factory()->create(['is_active' => false]);

        $this->get('/')
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
            ->assertSee('Contact for availability')
            ->assertSee('Current availability')
            ->assertDontSee('Book an appointment')
            ->assertDontSee('Instant confirmation');
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
}
