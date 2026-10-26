<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HostingReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HostingReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private array $originalPaths;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/casa-hosting-'.bin2hex(random_bytes(8));
        $this->originalPaths = [public_path(), storage_path(), app()->environmentPath(), database_path()];
        app()->usePublicPath($this->directory.'/public');
        app()->useStoragePath($this->directory.'/storage');
        app()->useEnvironmentPath($this->directory);
        foreach (['public/build/assets', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs'] as $path) {
            File::makeDirectory($this->directory.'/'.$path, 0755, true);
        }
        file_put_contents($this->directory.'/.env', '');
        app()->instance('config_loaded_from_cache', true);
        symlink(storage_path('app/public'), public_path('storage'));
        file_put_contents(public_path('build/assets/app.js'), '// fixture');
        file_put_contents(public_path('build/assets/app.css'), '/* fixture */');
        file_put_contents(public_path('build/manifest.json'), json_encode([
            'resources/js/app.js' => ['file' => 'assets/app.js'],
            'resources/css/app.css' => ['file' => 'assets/app.css'],
        ], JSON_THROW_ON_ERROR));
        app()->detectEnvironment(fn () => 'production');
        config([
            'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://spa.hosting-check.com',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'casa.security.trusted_hosts' => ['^spa\.hosting-check\.com$'],
            'casa.security.force_https' => true, 'trustedproxy.proxies' => '',
            'session.secure' => true, 'session.http_only' => true,
            'session.driver' => 'database', 'cache.default' => 'database', 'queue.default' => 'sync',
            'mail.default' => 'smtp', 'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => 'smtp.hosting-check.com', 'mail.mailers.smtp.port' => 465,
            'mail.mailers.smtp.scheme' => 'smtps', 'mail.mailers.smtp.username' => 'hosting-test-user',
            'mail.mailers.smtp.password' => 'hosting-secret-must-not-print',
            'mail.from.address' => 'hello@hosting-check.com',
            'services.google.client_id' => 'test-client', 'services.google.client_secret' => 'google-secret-must-not-print',
            'services.google.redirect' => 'https://spa.hosting-check.com/auth/google/callback',
            'auth.super_admin_email' => 'owner@hosting-check.com',
        ]);
        User::factory()->create(['email' => 'owner@hosting-check.com', 'role' => User::ROLE_SUPER_ADMIN, 'is_active' => true, 'email_verified_at' => now()]);
    }

    protected function tearDown(): void
    {
        app()->usePublicPath($this->originalPaths[0]);
        app()->useStoragePath($this->originalPaths[1]);
        app()->useEnvironmentPath($this->originalPaths[2]);
        app()->useDatabasePath($this->originalPaths[3]);
        app()->detectEnvironment(fn () => 'testing');
        config(['database.default' => 'mysql']);
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_production_preflight_passes_without_changing_records_or_printing_secrets(): void
    {
        $before = DB::table('users')->count();
        $checks = app(HostingReadiness::class)->inspect()['checks'];
        $this->assertNotContains(false, $checks, implode(', ', array_keys(array_filter($checks, fn ($passed) => ! $passed))));
        $this->artisan('casa:check-hosting')
            ->expectsOutputToContain('Automated hosting checks passed')
            ->doesntExpectOutputToContain('hosting-secret-must-not-print')
            ->doesntExpectOutputToContain('google-secret-must-not-print')
            ->doesntExpectOutputToContain('owner@hosting-check.com')
            ->assertSuccessful();
        $this->assertSame($before, DB::table('users')->count());
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_preflight_rejects_unsafe_settings_and_missing_assets(): void
    {
        app()->instance('config_loaded_from_cache', false);
        config(['app.debug' => true, 'mail.mailers.smtp.host' => 'mailpit', 'session.secure' => false, 'trustedproxy.proxies' => '*']);
        unlink(public_path('build/assets/app.js'));
        $this->artisan('casa:check-hosting')
            ->expectsOutputToContain('[FAIL] Production environment with debugging disabled')
            ->expectsOutputToContain('[FAIL] Only explicit proxy addresses or an empty proxy list')
            ->expectsOutputToContain('[FAIL] Authenticated production SMTP configured')
            ->expectsOutputToContain('[FAIL] Laravel configuration cache built')
            ->expectsOutputToContain('[FAIL] Compiled assets present without a development hot file')
            ->assertFailed();
    }

    public function test_preflight_detects_pending_migrations_without_applying_them(): void
    {
        $database = $this->directory.'/database';
        File::makeDirectory($database.'/migrations', 0755, true);
        file_put_contents($database.'/migrations/2099_01_01_000000_pending.php', '<?php // must not execute');
        app()->useDatabasePath($database);
        $before = DB::table('migrations')->count();
        $this->artisan('casa:check-hosting')->expectsOutputToContain('[FAIL] No pending application migrations')->assertFailed();
        $this->assertSame($before, DB::table('migrations')->count());
    }

    public function test_connection_failures_do_not_disclose_connection_settings(): void
    {
        config(['database.connections.hosting_unreachable' => [
            ...config('database.connections.mysql'), 'host' => '127.0.0.1', 'port' => 1,
            'password' => 'connection-secret-must-not-print', 'url' => null,
        ], 'database.default' => 'hosting_unreachable']);
        $this->artisan('casa:check-hosting')
            ->expectsOutputToContain('[FAIL] Application database checks completed')
            ->doesntExpectOutputToContain('connection-secret-must-not-print')
            ->doesntExpectOutputToContain('SQLSTATE')
            ->assertFailed();
    }
}
