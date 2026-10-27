<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['auth.super_admin_email' => 'owner@casaparaiso.test']);
    }

    public function test_admins_list_staff_accounts_but_never_customers(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        User::factory()->staff()->create(['name' => 'Tess Therapist', 'show_on_org_chart' => true, 'org_chart_tier' => User::ORG_TIER_TEAM]);
        User::factory()->receptionist()->create(['name' => 'Rae Reception']);
        User::factory()->customer()->create(['name' => 'Cora Customer']);

        $this->actingAs($admin)->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('Team page')
            ->assertSee('Tess Therapist')
            ->assertSee('Rae Reception')
            ->assertSee('Ada Admin')
            ->assertDontSee('Cora Customer')
            ->assertSee(route('admin.team.edit', User::query()->where('name', 'Tess Therapist')->first()), false);

        $this->actingAs($admin)->get(route('admin.team.index', ['visibility' => 'shown']))
            ->assertOk()
            ->assertSee('Tess Therapist')
            ->assertDontSee('Rae Reception');
    }

    public function test_non_admin_roles_cannot_manage_team_cards(): void
    {
        $member = User::factory()->staff()->create();

        foreach ([User::factory()->receptionist()->create(), User::factory()->staff()->create(), User::factory()->customer()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.team.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.team.edit', $member))->assertForbidden();
            $this->actingAs($user)->patch(route('admin.team.update', $member), ['public_title' => 'Hacked'])->assertForbidden();
        }

        $this->assertNull($member->fresh()->public_title);
    }

    public function test_customer_accounts_have_no_team_card(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin)->get(route('admin.team.edit', $customer))->assertNotFound();
        $this->actingAs($admin)->patch(route('admin.team.update', $customer), [
            'show_on_org_chart' => '1', 'org_chart_tier' => User::ORG_TIER_TEAM,
        ])->assertNotFound();

        $this->assertFalse($customer->fresh()->show_on_org_chart);
    }

    public function test_only_the_super_admin_can_edit_the_super_admin_card(): void
    {
        $owner = User::factory()->create(['email' => 'owner@casaparaiso.test', 'role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.team.edit', $owner))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.team.update', $owner), ['public_title' => 'Changed'])->assertForbidden();

        $this->actingAs($owner)->patch(route('admin.team.update', $owner), [
            'public_title' => 'Founder', 'show_on_org_chart' => '1', 'org_chart_tier' => User::ORG_TIER_OWNER, 'org_chart_order' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.team.index'));

        $this->assertSame('Founder', $owner->fresh()->public_title);
    }

    public function test_admin_publishes_a_card_with_a_cropped_photo(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->staff()->create();

        $this->actingAs($admin)->get(route('admin.team.edit', $member))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="org_chart_tier"', false);

        $this->actingAs($admin)->patch(route('admin.team.update', $member), [
            'photo' => UploadedFile::fake()->image('portrait.jpg', 1200, 1600),
            'public_title' => 'Senior Therapist',
            'public_bio' => 'Specialises in hot stone treatments.',
            'show_on_org_chart' => '1',
            'org_chart_tier' => User::ORG_TIER_LEADERSHIP,
            'org_chart_order' => '3',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.team.index'))->assertSessionHas('status', 'team-card-updated');

        $member->refresh();
        $this->assertSame('Senior Therapist', $member->public_title);
        $this->assertSame(User::ORG_TIER_LEADERSHIP, $member->org_chart_tier);
        $this->assertSame(3, $member->org_chart_order);
        $this->assertTrue($member->show_on_org_chart);
        $this->assertStringStartsWith('user-photos/', $member->photo_path);
        $info = getimagesizefromstring(Storage::disk('public')->get($member->photo_path));
        $this->assertSame('image/webp', $info['mime']);
        $this->assertSame([480, 480], [$info[0], $info[1]]);
    }

    public function test_replacing_and_removing_a_photo_cleans_up_old_files(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->staff()->create();

        $this->actingAs($admin)->patch(route('admin.team.update', $member), ['photo' => UploadedFile::fake()->image('first.png', 300, 300)])->assertSessionHasNoErrors();
        $first = $member->fresh()->photo_path;

        $this->actingAs($admin)->patch(route('admin.team.update', $member), ['photo' => UploadedFile::fake()->image('second.png', 200, 120)])->assertSessionHasNoErrors();
        $second = $member->fresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        $info = getimagesizefromstring(Storage::disk('public')->get($second));
        $this->assertSame([120, 120], [$info[0], $info[1]]);

        $this->actingAs($admin)->patch(route('admin.team.update', $member), ['remove_photo' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($member->fresh()->photo_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_invalid_photos_and_missing_levels_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->staff()->create();

        $this->actingAs($admin)->from(route('admin.team.edit', $member))->patch(route('admin.team.update', $member), [
            'photo' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            'show_on_org_chart' => '1',
            'org_chart_tier' => '',
            'public_title' => str_repeat('x', 81),
        ])->assertSessionHasErrors(['photo', 'org_chart_tier', 'public_title']);

        $this->assertFalse($member->fresh()->show_on_org_chart);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_photos_replace_initials_across_workspace_avatars(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);

        $this->actingAs($admin)->patch(route('admin.team.update', $admin), ['photo' => UploadedFile::fake()->image('me.png', 200, 200)])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($admin->fresh()->photo_path), false)
            ->assertSee('has-photo', false);
    }
}
