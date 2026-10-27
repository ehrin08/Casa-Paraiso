<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTeamCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_staff_see_and_update_their_own_team_card(): void
    {
        $therapist = User::factory()->staff()->create();

        $this->actingAs($therapist)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Public team card')
            ->assertSee(route('profile.team-card.update'), false)
            ->assertDontSee('name="show_on_org_chart"', false)
            ->assertDontSee('name="org_chart_tier"', false);

        $this->actingAs($therapist)->patch(route('profile.team-card.update'), [
            'photo' => UploadedFile::fake()->image('me.jpg', 600, 400),
            'public_title' => 'Massage Therapist',
            'public_bio' => 'Ten years of Swedish and hot stone massage.',
            // Admin-only placement fields are ignored here.
            'show_on_org_chart' => '1',
            'org_chart_tier' => User::ORG_TIER_OWNER,
            'org_chart_order' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'))->assertSessionHas('status', 'team-card-updated');

        $therapist->refresh();
        $this->assertSame('Massage Therapist', $therapist->public_title);
        $this->assertSame('Ten years of Swedish and hot stone massage.', $therapist->public_bio);
        $this->assertFalse($therapist->show_on_org_chart);
        $this->assertNull($therapist->org_chart_tier);
        Storage::disk('public')->assertExists($therapist->photo_path);
    }

    public function test_staff_can_remove_their_own_photo(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $this->actingAs($receptionist)->patch(route('profile.team-card.update'), ['photo' => UploadedFile::fake()->image('me.png', 100, 100)])->assertSessionHasNoErrors();
        $path = $receptionist->fresh()->photo_path;

        $this->actingAs($receptionist)->patch(route('profile.team-card.update'), ['remove_photo' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($receptionist->fresh()->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_customers_have_no_team_card(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('profile.edit'))->assertOk()->assertDontSee('Public team card');
        $this->actingAs($customer)->patch(route('profile.team-card.update'), ['public_title' => 'Therapist'])->assertForbidden();

        $this->assertNull($customer->fresh()->public_title);
    }
}
