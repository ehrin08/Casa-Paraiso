<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_roles_see_the_reference_sections_and_one_save_action(): void
    {
        config(['auth.super_admin_email' => 'owner@casaparaiso.test']);

        foreach ([User::factory()->admin()->create(), User::factory()->create([
            'email' => 'owner@casaparaiso.test', 'role' => User::ROLE_SUPER_ADMIN,
        ])] as $user) {
            $response = $this->actingAs($user)->get(route('admin.settings.index'))
                ->assertOk()
                ->assertSeeInOrder(['Business identity', 'Company history', 'Payments', 'Environment checks', 'Public organization chart'])
                ->assertSee('More public details')
                ->assertSee('Operating rules and user access')
                ->assertSee('No public organization chart yet.')
                ->assertSee('Debug mode')
                ->assertSee('HTTPS cookies')
                ->assertSee('Session encryption')
                ->assertSee('Trusted hosts')
                ->assertDontSee('Healthy');

            $xpath = $this->settingsXPath($response->getContent());
            $this->assertSame(1, $xpath->query('//form[@data-settings-workspace]//button[@type="submit"]')->length);
            $this->assertSame('Save changes', trim($xpath->query('//form[@data-settings-workspace]//button[@type="submit"]')->item(0)->textContent));
            $this->assertSame(0, $xpath->query('//details[@id="settings-chart-editor" or @id="settings-public-details"][@open]')->length);
            $this->assertSame(1, $xpath->query('//form[@data-settings-workspace]//dialog[@data-settings-chart-dialog][@open]')->length);
            $this->assertSame(0, $xpath->query('//dialog//form')->length);
            $this->assertSame(2, $xpath->query('//dialog//input[@name="organization_chart[owner][title]" or @name="organization_chart[admin][title]"]')->length);
            $this->assertSame(8, $xpath->query('//dialog//noscript//input[contains(@name, "[title]")]')->length);
            $this->assertSame(2, $xpath->query('//dialog//input[@type="file"][not(ancestor::template)][not(ancestor::noscript)]')->length);
            $this->assertSame(0, $xpath->query('//dialog//button[@type="submit"]')->length);
        }
    }

    public function test_a_no_javascript_unrelated_save_preserves_the_collapsed_chart_and_photos(): void
    {
        Storage::fake('public');
        $chart = [
            'owner' => ['title' => 'Owner', 'name' => 'Ana', 'description' => 'Leads the spa.', 'photo' => 'org-chart/owner.webp'],
            'admin' => ['title' => 'Administrator', 'name' => 'Bea', 'description' => 'Runs operations.', 'photo' => 'org-chart/admin.webp'],
            'team' => [
                ['title' => 'Therapists', 'name' => 'Care team', 'description' => 'Treatments.', 'photo' => 'org-chart/team-0.webp'],
                ['title' => 'Reception', 'name' => 'Welcome team', 'description' => 'Arrivals.', 'photo' => 'org-chart/team-1.webp'],
            ],
        ];
        foreach (['owner.webp', 'admin.webp', 'team-0.webp', 'team-1.webp'] as $file) {
            Storage::disk('public')->put('org-chart/'.$file, 'retained-photo');
        }
        $settings = ApplicationSetting::factory()->create(['id' => 1, 'organization_chart' => $chart]);
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->get(route('admin.settings.index'))->assertOk()->assertSee('data-settings-chart-preview', false);
        $payload = $this->noJavascriptFormPayload($response->getContent());
        $payload['contact_phone'] = '09171234567';

        $this->patch(route('admin.settings.update'), $payload)->assertSessionHasNoErrors()->assertSessionHas('status', 'settings-updated');

        $this->assertSame($chart, $settings->fresh()->organization_chart);
        $this->assertSame('09171234567', $settings->fresh()->contact_phone);
        foreach (['owner.webp', 'admin.webp', 'team-0.webp', 'team-1.webp'] as $file) {
            Storage::disk('public')->assertExists('org-chart/'.$file);
        }
    }

    public function test_chart_photo_upload_replacement_and_removal_keep_the_existing_workflow(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $payload = $this->noJavascriptFormPayload($this->get(route('admin.settings.index'))->assertOk()->getContent());
        $payload['organization_chart'] = [
            'owner' => ['title' => 'Owner'], 'admin' => ['title' => 'Administrator'],
            'team' => [['title' => 'Therapists']],
        ];
        $payload['organization_chart_photos'] = [
            'owner' => UploadedFile::fake()->image('owner.png'),
            'admin' => UploadedFile::fake()->image('admin.jpg'),
            'team' => [UploadedFile::fake()->image('team.png')],
        ];
        $this->patch(route('admin.settings.update'), $payload)->assertSessionHasNoErrors();
        foreach (['owner.webp', 'admin.webp', 'team-0.webp'] as $file) {
            Storage::disk('public')->assertExists('org-chart/'.$file);
        }

        $ownerBefore = Storage::disk('public')->get('org-chart/owner.webp');
        $payload = $this->noJavascriptFormPayload($this->get(route('admin.settings.index'))->assertOk()->getContent());
        $payload['organization_chart_photos']['owner'] = UploadedFile::fake()->image('replacement.png', 300, 100);
        $replacement = imagecreatetruecolor(300, 100);
        imagefill($replacement, 0, 0, imagecolorallocate($replacement, 80, 110, 80));
        imagepng($replacement, $payload['organization_chart_photos']['owner']->getRealPath());
        imagedestroy($replacement);
        $payload['organization_chart_remove_photos'] = ['admin' => '1', 'team' => ['1']];
        $this->patch(route('admin.settings.update'), $payload)->assertSessionHasNoErrors();

        $chart = ApplicationSetting::current()->organization_chart;
        $this->assertSame('org-chart/owner.webp', $chart['owner']['photo']);
        $this->assertNotSame($ownerBefore, Storage::disk('public')->get('org-chart/owner.webp'));
        $this->assertNull($chart['admin']['photo']);
        $this->assertNull($chart['team'][0]['photo']);
        Storage::disk('public')->assertMissing(['org-chart/admin.webp', 'org-chart/team-0.webp']);
    }

    public function test_validation_opens_both_disclosures_and_retains_draft_chart_values(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $payload = $this->noJavascriptFormPayload($this->get(route('admin.settings.index'))->assertOk()->getContent());
        $payload['facebook_url'] = 'https://example.com/private';
        $payload['organization_chart'] = [
            'owner' => ['title' => 'Draft owner', 'name' => 'Draft name'],
            'admin' => ['title' => 'Administrator'],
            'team' => [['title' => 'Therapists', 'name' => str_repeat('x', 101)]],
        ];
        $payload['organization_chart_photos']['owner'] = UploadedFile::fake()->create('invalid.txt', 1, 'text/plain');
        $this->from(route('admin.settings.index'))->patch(route('admin.settings.update'), $payload)
            ->assertSessionHasErrors(['facebook_url', 'organization_chart.team.0.name', 'organization_chart_photos.owner']);
        $response = $this->get(route('admin.settings.index'))->assertOk()->assertSee('Draft owner')->assertSee('Draft name');
        $xpath = $this->settingsXPath($response->getContent());
        $this->assertSame(2, $xpath->query('//details[@id="settings-chart-editor" or @id="settings-public-details"][@open]')->length);
        $this->assertSame('Draft owner', $xpath->query('//dialog//input[@name="organization_chart[owner][title]"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//dialog[@data-settings-chart-dialog][@open]')->length);
        $this->assertDatabaseCount('application_settings', 0);
    }

    public function test_chart_validation_retains_photo_removal_choices_inside_the_editor(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('org-chart/owner.webp', 'retained-photo');
        $settings = ApplicationSetting::factory()->create(['id' => 1, 'organization_chart' => [
            'owner' => ['title' => 'Owner', 'photo' => 'org-chart/owner.webp'],
            'team' => [],
        ]]);
        $this->actingAs(User::factory()->admin()->create());
        $payload = $this->noJavascriptFormPayload($this->get(route('admin.settings.index'))->getContent());
        $payload['organization_chart']['owner']['name'] = str_repeat('x', 101);
        $payload['organization_chart_remove_photos']['owner'] = '1';
        $this->from(route('admin.settings.index'))->patch(route('admin.settings.update'), $payload)
            ->assertSessionHasErrors('organization_chart.owner.name');

        $xpath = $this->settingsXPath($this->get(route('admin.settings.index'))->getContent());
        $this->assertSame(1, $xpath->query('//details[@id="settings-chart-editor"][@open]//dialog//input[@name="organization_chart_remove_photos[owner]"][@checked]')->length);
        $this->assertSame('org-chart/owner.webp', $settings->fresh()->organization_chart['owner']['photo']);
        Storage::disk('public')->assertExists('org-chart/owner.webp');
    }

    private function settingsXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }

    /** Read the successful HTML controls as a browser with scripting disabled would. */
    private function noJavascriptFormPayload(string $html): array
    {
        $xpath = $this->settingsXPath($html);
        $pairs = [];
        foreach ($xpath->query('//form[@data-settings-workspace]//*[self::input or self::textarea or self::select][@name][not(ancestor::template)][not(@disabled)]') as $control) {
            $type = $control->getAttribute('type');
            if ($type === 'file' || ($type === 'checkbox' && ! $control->hasAttribute('checked'))) {
                continue;
            }
            $value = match ($control->nodeName) {
                'textarea' => $control->textContent,
                'select' => $xpath->query('.//option[@selected]', $control)->item(0)?->getAttribute('value') ?? '',
                default => $control->getAttribute('value'),
            };
            $pairs[] = urlencode($control->getAttribute('name')).'='.urlencode($value);
        }
        parse_str(implode('&', $pairs), $payload);

        return $payload;
    }

    public function test_admin_can_view_and_update_application_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Business identity')
            ->assertSee('Company history')
            ->assertSee('Public organization chart')
            ->assertSee('Environment checks')
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
                'company_history' => 'Casa Paraiso began with a commitment to thoughtful, restorative care.',
                'organization_chart' => [
                    'owner' => ['title' => 'Owner / Super Administrator', 'description' => 'Guides the spa mission.'],
                    'admin' => ['title' => 'Administrator', 'description' => 'Coordinates daily operations.'],
                    'team' => [
                        ['title' => 'Receptionist', 'description' => 'Welcomes and assists guests.'],
                        ['title' => 'Therapists', 'description' => 'Deliver each wellness ritual.'],
                    ],
                ],
                'organization_chart_remove_photos' => [
                    'owner' => '',
                    'admin' => '',
                    'team' => [0 => ''],
                ],
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

        $settings = ApplicationSetting::query()->firstOrFail();

        $this->assertSame('Casa Paraiso began with a commitment to thoughtful, restorative care.', $settings->company_history);
        $this->assertSame('Owner / Super Administrator', data_get($settings->organization_chart, 'owner.title'));
        $this->assertSame('Therapists', data_get($settings->organization_chart, 'team.1.title'));
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
                'company_history' => str_repeat('a', 6001),
                'organization_chart' => [
                    'owner' => ['title' => '', 'description' => str_repeat('a', 301)],
                    'admin' => ['title' => str_repeat('a', 101), 'description' => null],
                    'team' => array_fill(0, 9, ['title' => 'Team role', 'description' => null]),
                ],
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
                'company_history',
                'organization_chart.owner.title',
                'organization_chart.owner.description',
                'organization_chart.admin.title',
                'organization_chart.team',
                'default_payment_method',
            ]);

        $this->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Please review the highlighted settings and try again.');
    }

    public function test_admin_managed_company_profile_is_rendered_safely_on_the_landing_page(): void
    {
        ApplicationSetting::factory()->create([
            'id' => 1,
            'company_history' => "Founded around restorative care.\n\n<script>alert('unsafe')</script>",
            'organization_chart' => [
                'owner' => ['title' => 'Owner / Super Administrator', 'description' => 'Sets the direction.'],
                'admin' => ['title' => 'Administrator', 'description' => 'Leads daily operations.'],
                'team' => [
                    ['title' => 'Receptionist', 'description' => 'Supports every arrival.'],
                    ['title' => 'Therapists', 'description' => 'Provide wellness care.'],
                ],
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('href="#about"', false)
            ->assertSee('data-landing-company-profile', false)
            ->assertSee('data-company-history', false)
            ->assertSee('data-organization-chart', false)
            ->assertSee('Founded around restorative care.')
            ->assertSee('Owner / Super Administrator')
            ->assertSee('Administrator')
            ->assertSee('Receptionist')
            ->assertSee('Therapists')
            ->assertSee('&lt;script&gt;alert(&#039;unsafe&#039;)&lt;/script&gt;', false)
            ->assertDontSee("<script>alert('unsafe')</script>", false);
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
