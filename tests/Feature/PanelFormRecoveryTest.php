<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelFormRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_panel_submit_reopens_the_form_panel_with_errors_and_input(): void
    {
        $admin = User::factory()->admin()->create();
        $index = route('admin.addons.index', absolute: false);
        $create = route('admin.addons.create', absolute: false);

        $this->actingAs($admin)
            ->from($index)
            ->post(route('admin.addons.store', absolute: false), ['_panel' => $create, 'name' => '', 'description' => 'Kept description', 'duration_minutes' => 15, 'price' => 100])
            ->assertRedirect($index)
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->get($index)
            ->assertOk()
            ->assertSee('data-panel-restore="'.$create.'"', false);

        $this->actingAs($admin)
            ->withHeader('X-Casa-Panel', '1')
            ->get($create)
            ->assertOk()
            ->assertSee('Kept description')
            ->assertSee('The name field is required.');

        $this->actingAs($admin)
            ->get($index)
            ->assertOk()
            ->assertDontSee('data-panel-restore', false);
    }

    public function test_failed_service_edit_from_a_panel_restores_the_edit_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create(['name' => 'Signature Massage']);
        $index = route('admin.services.index', absolute: false);
        $edit = route('admin.services.edit', $service, false);

        $this->actingAs($admin)
            ->from($index)
            ->patch(route('admin.services.update', $service, false), ['_panel' => $edit, 'name' => 'Renamed', 'duration_minutes' => -5, 'price' => 500])
            ->assertRedirect($index)
            ->assertSessionHasErrors('duration_minutes');

        $this->actingAs($admin)->get($index)->assertOk()->assertSee('data-panel-restore="'.$edit.'"', false);

        $this->actingAs($admin)
            ->withHeader('X-Casa-Panel', '1')
            ->get($edit)
            ->assertOk()
            ->assertSee('value="Renamed"', false);

        $this->assertSame('Signature Massage', $service->fresh()->name);
    }

    public function test_page_without_errors_has_no_restore_marker(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withSession(['_old_input' => ['_panel' => '/admin/addons/create']])
            ->get(route('admin.addons.index', absolute: false))
            ->assertOk()
            ->assertDontSee('data-panel-restore', false);
    }

    #[DataProvider('unsafePaths')]
    public function test_unsafe_panel_paths_are_never_restored(string $path): void
    {
        $admin = User::factory()->admin()->create();
        $index = route('admin.addons.index', absolute: false);

        $this->actingAs($admin)
            ->from($index)
            ->post(route('admin.addons.store', absolute: false), ['_panel' => $path, 'name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->get($index)
            ->assertOk()
            ->assertDontSee('data-panel-restore', false);
    }

    /** @return array<string, array{string}> */
    public static function unsafePaths(): array
    {
        return [
            'protocol relative' => ['//evil.example/admin/addons/create'],
            'absolute url' => ['https://evil.example/admin/addons/create'],
            'outside panel prefixes' => ['/profile'],
            'script characters' => ['/admin/addons/create"><script>'],
            'backslash' => ['/admin/addons/\\evil'],
        ];
    }
}
