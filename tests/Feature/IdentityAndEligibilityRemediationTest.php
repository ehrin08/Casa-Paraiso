<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class IdentityAndEligibilityRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_removal_and_account_deactivation_are_blocked_for_future_confirmed_staff(): void
    {
        $superAdmin = $this->superAdmin();
        $service = Service::factory()->create();

        foreach (['role', 'active'] as $change) {
            $staffUser = User::factory()->staff()->create();
            $staffProfile = StaffProfile::factory()->for($staffUser)->create();
            $staffProfile->services()->attach($service);
            $this->futureConfirmedAppointment($staffProfile, $service);

            $response = $this->actingAs($superAdmin)
                ->from(route('admin.users.index', absolute: false))
                ->put(route('admin.users.update', $staffUser, absolute: false), [
                    'name' => $staffUser->name,
                    'email' => $staffUser->email,
                    'role' => $change === 'role' ? User::ROLE_ADMIN : User::ROLE_STAFF,
                    'is_active' => $change === 'active' ? '0' : '1',
                ]);

            $response
                ->assertRedirect(route('admin.users.index', absolute: false))
                ->assertSessionHasErrors('staff_eligibility');

            $staffUser->refresh();
            $this->assertSame(User::ROLE_STAFF, $staffUser->role);
            $this->assertTrue($staffUser->is_active);
            $this->assertFalse(StaffProfile::withTrashed()->findOrFail($staffProfile->id)->trashed());
        }
    }

    public function test_staff_editor_blocks_unbooking_and_required_service_removal(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();

        foreach (['bookable', 'service'] as $change) {
            $staffUser = User::factory()->staff()->create();
            $staffProfile = StaffProfile::factory()->for($staffUser)->create();
            $staffProfile->services()->attach($service);
            $this->futureConfirmedAppointment($staffProfile, $service);

            $response = $this->actingAs($admin)
                ->from(route('admin.staff.edit', $staffProfile, absolute: false))
                ->patch(route('admin.staff.update', $staffProfile, absolute: false), [
                    'name' => $staffUser->name,
                    'email' => $staffUser->email,
                    'is_active' => '1',
                    'is_bookable' => $change === 'bookable' ? '0' : '1',
                    'service_ids' => $change === 'service' ? [] : [$service->id],
                ]);

            $response
                ->assertRedirect(route('admin.staff.edit', $staffProfile, absolute: false))
                ->assertSessionHasErrors('staff_eligibility');

            $this->assertTrue($staffProfile->fresh()->is_bookable);
            $this->assertTrue($staffProfile->services()->whereKey($service->id)->exists());
        }
    }

    public function test_staff_index_editor_keeps_an_inactive_assigned_service_selectable(): void
    {
        $admin = User::factory()->admin()->create();
        $staffProfile = StaffProfile::factory()->create();
        $service = Service::factory()->create(['is_active' => false]);
        $staffProfile->services()->attach($service);

        $this->actingAs($admin)
            ->get(route('admin.staff.edit', $staffProfile, false))
            ->assertOk()
            ->assertSee('name="service_ids[]" value="'.$service->id.'"', false)
            ->assertSee($service->name)
            ->assertSee('(Inactive)');
    }

    public function test_role_changes_preserve_historical_customer_and_staff_identity_relationships(): void
    {
        $superAdmin = $this->superAdmin();
        $customerUser = User::factory()->customer()->create(['name' => 'Historic Customer']);
        $customerProfile = CustomerProfile::factory()->for($customerUser)->create();
        $staffUser = User::factory()->staff()->create(['name' => 'Historic Therapist']);
        $staffProfile = StaffProfile::factory()->for($staffUser)->create();
        $service = Service::factory()->create();
        $appointment = Appointment::factory()
            ->for($customerProfile, 'customerProfile')
            ->for($service)
            ->for($staffProfile, 'staffProfile')
            ->create([
                'status' => Appointment::STATUS_COMPLETED,
                'requested_start_at' => now()->subDay(),
                'scheduled_start_at' => now()->subDay(),
                'scheduled_end_at' => now()->subDay()->addHour(),
                'completed_at' => now()->subDay()->addHour(),
            ]);
        $transaction = Transaction::factory()
            ->for($customerProfile, 'customerProfile')
            ->for($appointment)
            ->for($service)
            ->create();
        $feedback = Feedback::factory()
            ->for($customerProfile, 'customerProfile')
            ->for($appointment)
            ->for($service)
            ->create();
        $promotion = PromotionSuggestion::factory()
            ->for($customerProfile, 'customerProfile')
            ->create();

        foreach ([[$staffUser, User::ROLE_ADMIN], [$customerUser, User::ROLE_ADMIN]] as [$managedUser, $newRole]) {
            $this->actingAs($superAdmin)
                ->put(route('admin.users.update', $managedUser, absolute: false), [
                    'name' => $managedUser->name,
                    'email' => $managedUser->email,
                    'role' => $newRole,
                    'is_active' => '1',
                ])
                ->assertRedirect();
        }

        $this->assertTrue(StaffProfile::withTrashed()->findOrFail($staffProfile->id)->trashed());
        $this->assertTrue(CustomerProfile::withTrashed()->findOrFail($customerProfile->id)->trashed());
        $this->assertSame('Historic Therapist', $appointment->fresh()->staffProfile?->user?->name);
        $this->assertSame('Historic Customer', $appointment->fresh()->customerProfile?->user?->name);
        $this->assertSame('Historic Customer', $transaction->fresh()->customerProfile?->user?->name);
        $this->assertSame('Historic Customer', $feedback->fresh()->customerProfile?->user?->name);
        $this->assertSame('Historic Customer', $promotion->fresh()->customerProfile?->user?->name);

        $this->actingAs($superAdmin)
            ->get(route('admin.appointments.show', $appointment, absolute: false))
            ->assertOk()
            ->assertSee('Historic Customer')
            ->assertSee('Historic Therapist');
    }

    public function test_customer_can_update_address_and_contact_preference(): void
    {
        $customer = User::factory()->customer()->create();
        $profile = CustomerProfile::factory()->for($customer)->create([
            'address' => null,
            'contact_preference' => null,
        ]);

        $this->actingAs($customer)->patch(route('profile.update', absolute: false), [
            'name' => 'Updated Customer',
            'phone' => '09171234567',
            'address' => '123 Wellness Street',
            'contact_preference' => CustomerProfile::CONTACT_SMS,
        ])->assertRedirect(route('profile.edit', absolute: false));

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Updated Customer',
            'phone' => '09171234567',
        ]);
        $this->assertDatabaseHas('customer_profiles', [
            'id' => $profile->id,
            'address' => '123 Wellness Street',
            'contact_preference' => CustomerProfile::CONTACT_SMS,
        ]);

        $this->actingAs($customer)->from(route('profile.edit', absolute: false))->patch(route('profile.update', absolute: false), [
            'name' => 'Updated Customer',
            'contact_preference' => 'carrier-pigeon',
        ])->assertRedirect(route('profile.edit', absolute: false))->assertSessionHasErrors('contact_preference');

        $this->assertSame(CustomerProfile::CONTACT_SMS, $profile->fresh()->contact_preference);
    }

    public function test_super_admin_cannot_change_a_google_linked_email_but_can_change_an_unlinked_email(): void
    {
        $superAdmin = $this->superAdmin();
        $linked = User::factory()->staff()->create([
            'email' => 'linked@example.com',
            'google_id' => 'google-linked',
        ]);

        $this->actingAs($superAdmin)->put(route('admin.users.update', $linked, absolute: false), [
            'name' => $linked->name,
            'email' => 'changed@example.com',
            'role' => User::ROLE_STAFF,
            'is_active' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertSame('linked@example.com', $linked->fresh()->email);

        $unlinked = User::factory()->staff()->create([
            'email' => 'unlinked@example.com',
            'google_id' => null,
        ]);

        $this->actingAs($superAdmin)->put(route('admin.users.update', $unlinked, absolute: false), [
            'name' => $unlinked->name,
            'email' => 'preauthorized@example.com',
            'role' => User::ROLE_STAFF,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('preauthorized@example.com', $unlinked->fresh()->email);
    }

    public function test_google_sign_in_is_the_only_path_that_updates_a_linked_email(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'old@example.com',
            'google_id' => 'stable-google-id',
        ]);
        CustomerProfile::factory()->for($customer)->create();
        $this->mockGoogle('stable-google-id', 'Google Customer', 'new@example.com');

        $this->get(route('auth.google.callback', absolute: false))->assertRedirect(route('customer.appointments.index', absolute: false));

        $this->assertSame('new@example.com', $customer->fresh()->email);
    }

    public function test_valid_deletion_confirmation_deidentifies_customer_and_preserves_business_records(): void
    {
        Carbon::setTestNow('2026-07-12 12:00:00');
        $customer = User::factory()->customer()->create([
            'name' => 'Private Customer',
            'email' => 'private@example.com',
            'google_id' => 'google-private',
            'phone' => '09171234567',
        ]);
        $profile = CustomerProfile::factory()->for($customer)->create([
            'birth_date' => '1990-01-01',
            'address' => 'Private address',
            'contact_preference' => CustomerProfile::CONTACT_PHONE,
            'notes' => 'Private care note',
        ]);
        $service = Service::factory()->create();
        $appointment = Appointment::factory()->for($profile, 'customerProfile')->for($service)->create();
        $transaction = Transaction::factory()->for($profile, 'customerProfile')->for($appointment)->for($service)->create();
        $feedback = Feedback::factory()->for($profile, 'customerProfile')->for($appointment)->for($service)->create();
        $promotion = PromotionSuggestion::factory()->for($profile, 'customerProfile')->create();

        $this->actingAs($customer)
            ->withSession(['google_reauthenticated_for_deletion' => [
                'user_id' => $customer->id,
                'confirmed_at' => now()->timestamp,
            ]])
            ->delete(route('profile.destroy', absolute: false))
            ->assertRedirect('/');

        $this->assertGuest();
        $customer->refresh();
        $this->assertSame('Deleted customer', $customer->name);
        $this->assertSame("deleted-customer-{$customer->id}@accounts.invalid", $customer->email);
        $this->assertNull($customer->google_id);
        $this->assertNull($customer->phone);
        $this->assertNull($customer->password);
        $this->assertFalse($customer->is_active);

        $deletedProfile = CustomerProfile::withTrashed()->findOrFail($profile->id);
        $this->assertTrue($deletedProfile->trashed());
        $this->assertNull($deletedProfile->birth_date);
        $this->assertNull($deletedProfile->address);
        $this->assertNull($deletedProfile->contact_preference);
        $this->assertNull($deletedProfile->notes);

        foreach ([$appointment, $transaction, $feedback, $promotion] as $record) {
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id]);
            $this->assertSame('Deleted customer', $record->fresh()->customerProfile?->user?->name);
        }
    }

    public function test_deletion_confirmation_is_single_use_and_expires(): void
    {
        Carbon::setTestNow('2026-07-12 12:00:00');
        config()->set('auth.profile_deletion_reauth_ttl', 600);
        $customer = User::factory()->customer()->create(['google_id' => 'google-customer']);
        CustomerProfile::factory()->for($customer)->create();

        $this->actingAs($customer)
            ->withSession(['google_reauthenticated_for_deletion' => [
                'user_id' => $customer->id,
                'confirmed_at' => now()->subSeconds(601)->timestamp,
            ]])
            ->delete(route('profile.destroy', absolute: false))
            ->assertForbidden()
            ->assertSessionMissing('google_reauthenticated_for_deletion');

        $this->assertTrue($customer->fresh()->is_active);
        $this->assertFalse(CustomerProfile::withTrashed()->findOrFail($customer->customerProfile->id)->trashed());
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'email' => config('auth.super_admin_email'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    private function futureConfirmedAppointment(StaffProfile $staffProfile, Service $service): Appointment
    {
        $start = now()->addDay()->setTime(14, 0);

        return Appointment::factory()
            ->for($service)
            ->for($staffProfile, 'staffProfile')
            ->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start->copy()->addHour(),
                'confirmed_at' => now(),
            ]);
    }

    private function mockGoogle(string $id, string $name, string $email): void
    {
        $googleUser = new GoogleUser;
        $googleUser->id = $id;
        $googleUser->name = $name;
        $googleUser->email = $email;
        $googleUser->user = ['verified_email' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
}
