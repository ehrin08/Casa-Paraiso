<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\PromotionSuggestion;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeTestAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_accounts_without_deleting(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();

        $this->artisan('casa:purge-test-accounts')
            ->expectsOutputToContain('Dry run complete. No data was changed.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $staff->user_id]);
        $this->assertDatabaseHas('customer_profiles', ['id' => $customer->id]);
    }

    public function test_apply_keeps_administrators_and_listed_users(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $realTherapist = StaffProfile::factory()->create();
        $testTherapist = StaffProfile::factory()->create();
        $receptionist = User::factory()->receptionist()->create();
        $customer = CustomerProfile::factory()->create();
        $guest = CustomerProfile::createGuest('Test guest');
        $voucher = PromotionSuggestion::factory()->create(['customer_profile_id' => $customer->id]);

        $this->artisan('casa:purge-test-accounts', ['--keep' => [(string) $realTherapist->user_id], '--apply' => true, '--yes' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $realTherapist->user_id]);
        $this->assertDatabaseHas('staff_profiles', ['id' => $realTherapist->id]);

        $this->assertDatabaseMissing('users', ['id' => $testTherapist->user_id]);
        $this->assertDatabaseMissing('staff_profiles', ['id' => $testTherapist->id]);
        $this->assertDatabaseMissing('users', ['id' => $receptionist->id]);
        $this->assertDatabaseMissing('users', ['id' => $customer->user_id]);
        $this->assertDatabaseMissing('customer_profiles', ['id' => $customer->id]);
        $this->assertDatabaseMissing('customer_profiles', ['id' => $guest->id]);
        $this->assertDatabaseMissing('promotion_suggestions', ['id' => $voucher->id]);
    }

    public function test_refuses_while_operational_data_remains(): void
    {
        $appointment = Appointment::factory()->create();

        $this->artisan('casa:purge-test-accounts', ['--apply' => true, '--yes' => true])
            ->expectsOutputToContain('Run casa:reset-operational-data --apply first.')
            ->assertFailed();

        $this->assertDatabaseHas('customer_profiles', ['id' => $appointment->customer_profile_id]);
    }

    public function test_rejects_unknown_keep_ids(): void
    {
        $customer = CustomerProfile::factory()->create();

        $this->artisan('casa:purge-test-accounts', ['--keep' => ['999999'], '--apply' => true, '--yes' => true])
            ->expectsOutputToContain('Unknown user IDs in --keep: 999999.')
            ->assertFailed();

        $this->assertDatabaseHas('customer_profiles', ['id' => $customer->id]);
    }

    public function test_apply_requires_confirmation(): void
    {
        $customer = CustomerProfile::factory()->create();

        $this->artisan('casa:purge-test-accounts', ['--apply' => true])
            ->expectsConfirmation('Permanently delete the accounts marked "delete" and the guest customers?', 'no')
            ->expectsOutputToContain('Purge cancelled. No data was changed.')
            ->assertSuccessful();

        $this->assertDatabaseHas('customer_profiles', ['id' => $customer->id]);
    }
}
