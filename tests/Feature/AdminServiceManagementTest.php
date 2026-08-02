<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_access_service_management(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $service = Service::factory()->create();

        foreach ([
            ['GET', route('admin.services.index', absolute: false)],
            ['GET', route('admin.services.create', absolute: false)],
            ['GET', route('admin.services.show', $service, false)],
            ['GET', route('admin.services.edit', $service, false)],
            ['POST', route('admin.services.store', absolute: false)],
            ['PATCH', route('admin.services.update', $service, false)],
            ['PATCH', route('admin.services.toggle', $service, false)],
        ] as [$method, $uri]) {
            $this->actingAs($admin)->call($method, $uri)->assertStatus($method === 'POST' || $method === 'PATCH' ? 302 : 200);
            $this->actingAs($staff)->call($method, $uri)->assertForbidden();
            $this->actingAs($customer)->call($method, $uri)->assertForbidden();
        }
    }

    public function test_admin_can_create_a_service_with_generated_slug(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/services', [
            'name' => 'Casa Signature Hilot',
            'description' => 'A warm oil treatment for deep relaxation.',
            'duration_minutes' => 90,
            'price' => 1800,
            'is_active' => '1',
        ]);

        $service = Service::query()->where('name', 'Casa Signature Hilot')->firstOrFail();

        $response->assertRedirect(route('admin.services.show', $service, absolute: false));
        $this->assertDatabaseHas('services', [
            'name' => 'Casa Signature Hilot',
            'slug' => 'casa-signature-hilot',
            'duration_minutes' => 90,
            'price' => 1800,
            'is_active' => true,
        ]);
    }

    public function test_service_slugs_are_unique_when_names_repeat(): void
    {
        $admin = User::factory()->admin()->create();

        Service::factory()->create([
            'name' => 'Foot Spa',
            'slug' => 'foot-spa',
        ]);

        $this->actingAs($admin)->post('/admin/services', [
            'name' => 'Foot Spa',
            'description' => null,
            'duration_minutes' => 45,
            'price' => 650,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('services', [
            'name' => 'Foot Spa',
            'slug' => 'foot-spa-2',
        ]);
    }

    public function test_admin_can_update_service_details_and_regenerate_slug_when_name_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create([
            'name' => 'Old Body Scrub',
            'slug' => 'old-body-scrub',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.services.update', $service, false), [
            'name' => 'Botanical Body Scrub',
            'description' => 'Polished skin care treatment.',
            'duration_minutes' => 75,
            'price' => 1400,
        ])->assertRedirect(route('admin.services.show', $service, false));

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Botanical Body Scrub',
            'slug' => 'botanical-body-scrub',
            'duration_minutes' => 75,
            'price' => 1400,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_toggle_service_active_status(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.services.toggle', $service, false))
            ->assertRedirect(route('admin.services.index', absolute: false));

        $this->assertFalse($service->fresh()->is_active);
    }

    public function test_service_validation_errors_are_returned(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/admin/services/create')
            ->post('/admin/services', [
                'name' => '',
                'description' => str_repeat('x', 5001),
                'duration_minutes' => 5,
                'price' => -1,
            ])
            ->assertRedirect('/admin/services/create')
            ->assertSessionHasErrors(['name', 'description', 'duration_minutes', 'price']);
    }

    public function test_service_catalog_pages_render_service_information(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create([
            'name' => 'Aromatherapy Massage',
            'slug' => 'aromatherapy-massage',
            'duration_minutes' => 60,
            'price' => 1200,
        ]);

        $this->actingAs($admin)
            ->get('/admin/services')
            ->assertOk()
            ->assertSee('Aromatherapy Massage')
            ->assertSee('PHP 1,200.00')
            ->assertSee('Active');

        $this->actingAs($admin)
            ->get(route('admin.services.show', $service, false))
            ->assertOk()
            ->assertSee('Aromatherapy Massage')
            ->assertSee('aromatherapy-massage')
            ->assertSee('Ready for therapist assignment');
    }
}
