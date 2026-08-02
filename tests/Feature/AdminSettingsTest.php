<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_application_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Business profile')
            ->assertSee('Security readiness')
            ->assertSee('Default payment method');

        $this->actingAs($admin)
            ->patch(route('admin.settings.update'), [
                'business_name' => 'Casa Paraiso Wellness Center',
                'contact_email' => 'hello@casaparaiso.test',
                'contact_phone' => '09171234567',
                'business_address' => 'San Fernando, Pampanga',
                'location_landmarks' => 'Across the public market.',
                'facebook_url' => 'https://www.facebook.com/61579320037378',
                'messenger_url' => 'https://m.me/61579320037378',
                'map_url' => 'https://www.google.com/maps/search/?api=1&query=Casa+Paraiso',
                'default_payment_method' => Transaction::METHOD_GCASH,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'settings-updated');

        $this->assertDatabaseHas('application_settings', [
            'id' => 1,
            'business_name' => 'Casa Paraiso Wellness Center',
            'contact_email' => 'hello@casaparaiso.test',
            'location_landmarks' => 'Across the public market.',
            'messenger_url' => 'https://m.me/61579320037378',
            'default_payment_method' => Transaction::METHOD_GCASH,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_settings_validation_rejects_invalid_values(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->patch(route('admin.settings.update'), [
                'business_name' => '',
                'contact_email' => 'not-an-email',
                'contact_phone' => str_repeat('1', 51),
                'business_address' => str_repeat('a', 1001),
                'location_landmarks' => str_repeat('a', 501),
                'facebook_url' => 'http://facebook.com/61579320037378',
                'messenger_url' => 'https://facebook.com/messages',
                'map_url' => 'https://maps.example.com/casa-paraiso',
                'default_payment_method' => 'Cryptocurrency',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors([
                'business_name',
                'contact_email',
                'contact_phone',
                'business_address',
                'location_landmarks',
                'facebook_url',
                'messenger_url',
                'map_url',
                'default_payment_method',
            ]);
    }

    public function test_non_admin_roles_cannot_read_or_update_settings(): void
    {
        foreach ([
            User::factory()->receptionist()->create(),
            User::factory()->staff()->create(),
            User::factory()->customer()->create(),
        ] as $user) {
            $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();
            $this->actingAs($user)->patch(route('admin.settings.update'), [])->assertForbidden();
        }
    }

    public function test_super_admin_alone_receives_user_access_link(): void
    {
        config(['auth.super_admin_email' => 'owner@casaparaiso.test']);

        $superAdmin = User::factory()->create([
            'email' => 'owner@casaparaiso.test',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee(route('admin.users.index'), false);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee(route('admin.users.index'), false);
    }

    public function test_business_name_and_payment_default_are_used_by_workspaces(): void
    {
        ApplicationSetting::factory()->create([
            'id' => 1,
            'business_name' => 'Casa Paraiso Test Spa',
            'business_address' => 'Barangay Cuta East, Santa Teresita, Batangas, Philippines',
            'location_landmarks' => 'In front of Alfamart and PLDT; in the same building as BDO Network Bank.',
            'facebook_url' => 'https://www.facebook.com/61579320037378',
            'messenger_url' => 'https://m.me/61579320037378',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query=Casa+Paraiso',
            'default_payment_method' => Transaction::METHOD_GCASH,
        ]);

        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->receptionist()->create();

        $this->get('/')->assertOk()
            ->assertSee('Casa Paraiso Test Spa')
            ->assertSee('Barangay Cuta East, Santa Teresita, Batangas, Philippines')
            ->assertSee('Message us')
            ->assertSee('Get directions');

        foreach ([
            [$admin, route('admin.transactions.create')],
            [$receptionist, route('reception.transactions.create')],
        ] as [$user, $url]) {
            $response = $this->actingAs($user)
                ->get($url)
                ->assertOk();

            $this->assertMatchesRegularExpression(
                '/<option value="GCash"[^>]*selected(?:="selected")?[^>]*>GCash<\/option>/',
                $response->getContent(),
            );
        }
    }
}
