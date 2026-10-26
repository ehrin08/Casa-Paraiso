<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class HostingReadiness
{
    /** @return array{checks: array<string, bool>, database_version: ?string} */
    public function inspect(): array
    {
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $key = (string) config('app.key');
        $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $gd = function_exists('gd_info') ? gd_info() : [];
        $checks = [
            'PHP 8.2 or newer' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'Required PHP extensions' => collect(['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'mbstring', 'openssl', 'pdo_mysql', 'session', 'tokenizer', 'xml'])
                ->every(fn (string $extension): bool => extension_loaded($extension)),
            'GD JPEG, PNG and WebP support' => ($gd['JPEG Support'] ?? false) && ($gd['PNG Support'] ?? false) && ($gd['WebP Support'] ?? false),
            'Production environment with debugging disabled' => app()->isProduction() && ! config('app.debug'),
            'Final HTTPS application URL' => $this->publicHost($host) && parse_url($url, PHP_URL_SCHEME) === 'https'
                && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS)
                && ! parse_url($url, PHP_URL_QUERY) && ! parse_url($url, PHP_URL_FRAGMENT)
                && in_array(parse_url($url, PHP_URL_PATH), [null, '', '/'], true),
            'Valid retained application encryption key' => is_string($key) && Encrypter::supported($key, (string) config('app.cipher')),
            'Trusted hosts include the application domain' => collect(config('casa.security.trusted_hosts', []))
                ->contains(fn ($pattern): bool => is_string($pattern) && $host !== '' && @preg_match('{'.$pattern.'}i', $host) === 1),
            'Only explicit proxy addresses or an empty proxy list' => $this->explicitProxies(),
            'Secure HTTP-only sessions and HTTPS URLs' => config('session.secure') === true && config('session.http_only') === true && config('casa.security.force_https') === true,
            'Database sessions/cache and synchronous jobs' => config('session.driver') === 'database' && config('cache.default') === 'database' && config('queue.default') === 'sync',
            'Authenticated production SMTP configured' => config('mail.default') === 'smtp'
                && blank(config('mail.mailers.smtp.url')) && $this->publicHost((string) config('mail.mailers.smtp.host'))
                && filled(config('mail.mailers.smtp.username')) && filled(config('mail.mailers.smtp.password'))
                && in_array(config('mail.mailers.smtp.scheme'), ['smtp', 'smtps', null], true)
                && (int) config('mail.mailers.smtp.port') > 0 && (int) config('mail.mailers.smtp.port') <= 65535,
            'Production email sender configured' => $this->publicEmail((string) config('mail.from.address')),
            'Google OAuth credentials and login redirect configured' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret'))
                && config('services.google.redirect') === rtrim($url, '/').'/auth/google/callback',
            'Laravel configuration cache built' => app()->configurationIsCached(),
            'Private environment and storage outside public directory' => $this->outsidePublic(app()->environmentFilePath()) && $this->outsidePublic(storage_path()),
            'Writable Laravel runtime directories' => collect([storage_path('framework/cache/data'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache')])
                ->every(fn (string $path): bool => is_dir($path) && is_writable($path)),
            'Public storage link points to uploaded files' => is_link(public_path('storage')) && is_dir(storage_path('app/public'))
                && realpath(public_path('storage')) === realpath(storage_path('app/public')),
            'Compiled assets present without a development hot file' => ! is_file(public_path('hot')) && $this->assetsPresent(),
        ];

        $version = null;
        try {
            $connection = DB::connection();
            $connection->getPdo();
            $checks['Application database connection'] = true;
            $version = (string) $connection->selectOne('SELECT VERSION() AS version')->version;
            $checks['MariaDB through the mysql connection'] = config('database.default') === 'mysql' && $connection->getDriverName() === 'mysql' && str_contains($version, 'MariaDB');
            $migrator = app('migrator');
            $checks['No pending application migrations'] = $migrator->repositoryExists()
                && array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan()) === [];
            $email = strtolower((string) config('auth.super_admin_email'));
            $checks['Active designated super administrator exists'] = $this->publicEmail($email)
                && User::query()->whereRaw('LOWER(email) = ?', [$email])->where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->whereNotNull('email_verified_at')->exists();
            $checks['Read-only business integrity audit'] = Artisan::call('casa:audit-crud-integrity') === 0;
        } catch (Throwable) {
            // Never print connection errors, SQL bindings, usernames or credentials.
            $checks['Application database checks completed'] = false;
        }

        return ['checks' => $checks, 'database_version' => $version];
    }

    private function publicHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        foreach (['localhost', 'local', 'test', 'example', 'invalid', 'example.com', 'example.net', 'example.org'] as $placeholder) {
            if ($host === $placeholder || str_ends_with($host, '.'.$placeholder)) {
                return false;
            }
        }

        return true;
    }

    private function publicEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) && $this->publicHost(substr(strrchr($email, '@'), 1));
    }

    private function explicitProxies(): bool
    {
        $proxies = config('trustedproxy.proxies');
        $proxies = is_string($proxies) ? array_filter(array_map('trim', explode(',', $proxies))) : $proxies;
        if ($proxies === null) {
            return true;
        }
        if (! is_array($proxies)) {
            return false;
        }
        foreach ($proxies as $proxy) {
            if (! is_string($proxy)) {
                return false;
            }
            $parts = explode('/', $proxy);
            if (count($parts) > 2 || ! filter_var($parts[0], FILTER_VALIDATE_IP)) {
                return false;
            }
            if (isset($parts[1]) && (! ctype_digit($parts[1]) || (int) $parts[1] > (str_contains($parts[0], ':') ? 128 : 32))) {
                return false;
            }
        }

        return true;
    }

    private function outsidePublic(string $path): bool
    {
        $public = realpath(public_path());
        $resolved = realpath($path);

        return $public !== false && $resolved !== false && $resolved !== $public && ! str_starts_with($resolved, $public.DIRECTORY_SEPARATOR);
    }

    private function assetsPresent(): bool
    {
        try {
            $directory = realpath(public_path('build'));
            if ($directory === false || ! is_file($directory.'/manifest.json')) {
                return false;
            }
            $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($manifest) || $manifest === []) {
                return false;
            }
            foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
                if (! isset($manifest[$entry]['file'])) {
                    return false;
                }
            }
            foreach ($manifest as $entry) {
                if (! is_array($entry) || ! isset($entry['file'])) {
                    return false;
                }
                foreach ([$entry['file'], ...($entry['css'] ?? [])] as $file) {
                    $path = is_string($file) ? realpath($directory.'/'.$file) : false;
                    if ($path === false || ! str_starts_with($path, $directory.DIRECTORY_SEPARATOR) || ! is_file($path)) {
                        return false;
                    }
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
