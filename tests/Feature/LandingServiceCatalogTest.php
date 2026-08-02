<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee('PHP 750.00');
    }
}
