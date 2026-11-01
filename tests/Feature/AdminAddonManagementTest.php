<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAddonManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_toggle_an_addon(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->post('/admin/addons', ['name' => 'Foot Spa', 'description' => 'Warm foot soak.', 'duration_minutes' => 15, 'price' => 350, 'is_active' => '1']);
        $addon = Addon::query()->where('name', 'Foot Spa')->firstOrFail();
        $response->assertRedirect(route('admin.addons.show', $addon));
        $this->assertSame('foot-spa', $addon->code);
        $this->actingAs($admin)->patch(route('admin.addons.update', $addon, false), ['name' => 'Foot Spa Plus', 'description' => 'Updated.', 'duration_minutes' => 30, 'price' => 450])->assertRedirect(route('admin.addons.show', $addon));
        $this->assertDatabaseHas('addons', ['id' => $addon->id, 'name' => 'Foot Spa Plus', 'code' => 'foot-spa', 'duration_minutes' => 30, 'price' => 450, 'is_active' => false]);
        $this->actingAs($admin)->patch(route('admin.addons.toggle', $addon, false))->assertRedirect();
        $this->assertTrue($addon->fresh()->is_active);
    }

    public function test_admin_addon_pages_match_the_service_catalog_layout(): void
    {
        $admin = User::factory()->admin()->create();
        $addon = Addon::query()->create(['code' => 'hot-stone-boost', 'name' => 'Hot Stone Boost', 'description' => 'Extra warmth.', 'duration_minutes' => 15, 'price' => 9999, 'is_active' => true]);
        $appointment = \App\Models\Appointment::factory()->create();
        $appointment->addons()->create(['addon_code' => 'hot-stone-boost', 'addon_name' => 'Hot Stone Boost', 'price' => 9999, 'duration_minutes' => 15]);

        $this->actingAs($admin)->get(route('admin.addons.show', $addon))
            ->assertOk()
            ->assertSee('Hot Stone Boost')
            ->assertSee('hot-stone-boost')
            ->assertSee('1 record')
            ->assertSee(route('admin.addons.edit', $addon), false);

        $this->actingAs($admin)->get(route('admin.addons.edit', $addon))
            ->assertOk()
            ->assertSee('Code is automatic')
            ->assertSee(route('admin.addons.update', $addon), false)
            ->assertSee(route('admin.addons.show', $addon), false);

        $this->actingAs($admin)->get(route('admin.addons.create'))
            ->assertOk()
            ->assertSee('Create add-on');

        $this->actingAs($admin)->get(route('admin.addons.index', ['sort' => 'price', 'direction' => 'desc']))
            ->assertOk()
            ->assertSee('services-table', false)
            ->assertSeeInOrder(['Hot Stone Boost', 'Ventosa']);
    }

    public function test_non_admins_are_forbidden_from_addon_management(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get('/admin/addons')->assertForbidden();
        $this->actingAs($staff)->post('/admin/addons', [])->assertForbidden();
    }

    public function test_deactivated_addons_are_excluded_from_new_catalog_choices(): void
    {
        $addon = Addon::query()->where('code', 'ventosa')->firstOrFail();
        $addon->update(['is_active' => false]);
        $this->assertFalse(app(\App\Services\AppointmentAddons::class)->catalog()->contains('code', 'ventosa'));
        $this->assertSame('Ventosa', app(\App\Services\AppointmentAddons::class)->name('ventosa'));
    }
}
