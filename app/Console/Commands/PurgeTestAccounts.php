<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeTestAccounts extends Command
{
    protected $signature = 'casa:purge-test-accounts
        {--keep=* : User ID to keep besides administrators (repeatable)}
        {--connection= : Database connection to inspect and purge (defaults to the application connection)}
        {--apply : Apply the purge; without this flag the command is read-only}
        {--yes : Skip the interactive confirmation after --apply}';

    protected $description = 'Dry-run or delete staff, receptionist and customer accounts and guest customers, keeping administrators and the listed users. Run casa:reset-operational-data first.';

    private const OPERATIONAL_TABLES = ['appointments', 'transactions', 'therapist_commissions', 'feedback', 'staff_attendances'];

    public function handle(): int
    {
        $connectionName = $this->option('connection') ?: config('database.default');
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'mysql') {
            $this->error('This purge requires a MySQL connection. No data was changed.');

            return self::FAILURE;
        }

        $keep = collect((array) $this->option('keep'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => trim($value))
            ->filter(fn ($value) => $value !== '');

        if ($keep->contains(fn ($value) => ! ctype_digit($value))) {
            $this->error('--keep takes numeric user IDs. No data was changed.');

            return self::FAILURE;
        }

        $keep = $keep->map(fn ($value) => (int) $value)->unique()->values();
        $users = $connection->table('users')->orderBy('role')->orderBy('id')->get(['id', 'name', 'email', 'role']);
        $unknown = $keep->diff($users->pluck('id'));

        if ($unknown->isNotEmpty()) {
            $this->error('Unknown user IDs in --keep: '.$unknown->implode(', ').'. No data was changed.');

            return self::FAILURE;
        }

        $kept = fn (object $user): bool => in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true)
            || $keep->contains($user->id);
        $deleteIds = $users->reject($kept)->pluck('id');
        $guestProfileIds = $connection->table('customer_profiles')->whereNull('user_id')->pluck('id');
        $profileIds = $connection->table('customer_profiles')->whereIn('user_id', $deleteIds)->pluck('id')->merge($guestProfileIds);

        $this->info("Connection: {$connectionName}");
        $this->table(['ID', 'Role', 'Name', 'Email', 'Action'], $users
            ->map(fn (object $user): array => [$user->id, $user->role, $user->name, $user->email, $kept($user) ? 'keep' : 'delete'])
            ->all());
        $this->table(['Also removed', 'Rows'], [
            ['Guest customer profiles', $guestProfileIds->count()],
            ['Customer memberships', $connection->table('customer_memberships')->whereIn('customer_profile_id', $profileIds)->count()],
            ['Promotion suggestions', $connection->table('promotion_suggestions')->whereIn('customer_profile_id', $profileIds)->count()],
            ['Gift vouchers bought by them', $connection->table('gift_vouchers')->whereIn('purchaser_customer_profile_id', $profileIds)->count()],
        ]);

        $blocking = collect(self::OPERATIONAL_TABLES)
            ->filter(fn (string $table) => $connection->table($table)->exists());

        if ($blocking->isNotEmpty()) {
            $this->error('Operational data remains in: '.$blocking->implode(', ').'. Run casa:reset-operational-data --apply first. No data was changed.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info('Dry run complete. No data was changed. Use --apply after taking a verified backup/export.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('Permanently delete the accounts marked "delete" and the guest customers?')) {
            $this->warn('Purge cancelled. No data was changed.');

            return self::SUCCESS;
        }

        $connection->transaction(function () use ($connection, $deleteIds, $profileIds, $users): void {
            $connection->table('gift_vouchers')->whereIn('purchaser_customer_profile_id', $profileIds)->delete();
            $connection->table('promotion_suggestions')->whereIn('customer_profile_id', $profileIds)->delete();
            // Memberships, perk usages and referral credits cascade from the profile.
            $connection->table('customer_profiles')->whereIn('id', $profileIds)->delete();
            // Staff and customer profiles, schedules and service links cascade from the user.
            $connection->table('sessions')->whereIn('user_id', $deleteIds)->delete();
            $connection->table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $deleteIds)->delete();
            $connection->table('password_reset_tokens')
                ->whereIn('email', $users->whereIn('id', $deleteIds->all())->pluck('email'))
                ->delete();
            $connection->table('users')->whereIn('id', $deleteIds)->delete();
        });

        $this->info("Deleted {$deleteIds->count()} accounts and {$profileIds->count()} customer profiles. Administrators and kept users were preserved.");

        return self::SUCCESS;
    }
}
