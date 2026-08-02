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
        $this->actingAs($admin)->post('/admin/addons', ['name' => 'Foot Spa', 'description' => 'Warm foot soak.', 'duration_minutes' => 15, 'price' => 350, 'is_active' => '1'])->assertRedirect();
        $addon = Addon::query()->where('name', 'Foot Spa')->firstOrFail();
        $this->assertSame('foot-spa', $addon->code);
        $this->actingAs($admin)->patch(route('admin.addons.update', $addon, false), ['name' => 'Foot Spa Plus', 'description' => 'Updated.', 'duration_minutes' => 30, 'price' => 450])->assertRedirect();
        $this->assertDatabaseHas('addons', ['id' => $addon->id, 'name' => 'Foot Spa Plus', 'code' => 'foot-spa', 'duration_minutes' => 30, 'price' => 450, 'is_active' => false]);
        $this->actingAs($admin)->patch(route('admin.addons.toggle', $addon, false))->assertRedirect();
        $this->assertTrue($addon->fresh()->is_active);
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
