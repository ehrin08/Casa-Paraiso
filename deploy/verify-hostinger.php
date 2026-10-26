<?php

// Verify the actual ZIP with production-only dependencies and a synthetic .env.
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';
$root = realpath(dirname(__DIR__));
$archive = realpath($argv[1] ?? '');
if ($archive === false || ! str_starts_with($archive, $root.'/output/hostinger-release/') || ! str_ends_with($archive, '.zip')) {
    throw new RuntimeException('Expected an archive inside output/hostinger-release.');
}
$expected = substr(trim(file_get_contents($archive.'.sha256')), 0, 64);
if (! preg_match('/^[a-f0-9]{64}$/', $expected) || ! hash_equals($expected, hash_file('sha256', $archive))) {
    throw new RuntimeException('Archive checksum verification failed.');
}
$zip = new ZipArchive;
if ($zip->open($archive, ZipArchive::CHECKCONS) !== true) {
    throw new RuntimeException('Archive integrity check failed.');
}
for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = $zip->getNameIndex($index);
    $zip->getExternalAttributesIndex($index, $system, $attributes);
    if (str_starts_with($name, '/') || str_contains($name, '\\') || in_array('..', explode('/', $name), true)
        || (($attributes >> 16) & 0170000) === 0120000
        || (! str_starts_with($name, 'casa-paraiso/') && ! in_array($name, ['RELEASE.json', 'HOSTINGER_DEPLOYMENT.md'], true))) {
        throw new RuntimeException('Unsafe or unexpected archive entry.');
    }
}
foreach (['casa-paraiso/.env', 'casa-paraiso/public/hot', 'casa-paraiso/bootstrap/cache/config.php', 'casa-paraiso/vendor/phpunit/phpunit/phpunit'] as $excluded) {
    if ($zip->locateName($excluded) !== false) {
        throw new RuntimeException('Archive contains an environment, local cache or development dependency.');
    }
}
$template = $zip->getFromName('casa-paraiso/.env.example');
if ($template === false || ! str_contains($template, 'APP_ENV=production') || ! str_contains($template, 'APP_DEBUG=false') || str_contains($template, 'DB_USERNAME=sail')) {
    throw new RuntimeException('Production environment template is missing or unsafe.');
}
$directory = sys_get_temp_dir().'/casa-hostinger-smoke-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$server = null;
try {
    if (! $zip->extractTo($directory)) {
        throw new RuntimeException('Unable to unpack the release.');
    }
    $zip->close();
    $app = $directory.'/casa-paraiso';
    $installed = json_decode(file_get_contents($app.'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    if (($installed['dev'] ?? true) !== false) {
        throw new RuntimeException('Archive vendor is not a production-only installation.');
    }
    symlink($app.'/public', $directory.'/public_html');
    symlink($app.'/storage/app/public', $app.'/public/storage');
    file_put_contents($app.'/storage/app/public/hostinger-probe.txt', "hostinger storage probe\n");
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $profile = [
        'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_URL' => 'http://127.0.0.1:'.$port, 'APP_TIMEZONE' => 'Asia/Manila',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1',
        'DB_DATABASE' => 'unreachable_fixture', 'DB_USERNAME' => 'fixture', 'DB_PASSWORD' => 'package-smoke-secret',
        'DB_URL' => '', 'DB_SOCKET' => '', 'MYSQL_ATTR_SSL_CA' => '',
        'SESSION_DRIVER' => 'file', 'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array', 'MAIL_HOST' => '', 'MAIL_URL' => '', 'GOOGLE_CLIENT_ID' => '', 'GOOGLE_CLIENT_SECRET' => '',
        'FORCE_HTTPS' => 'false', 'TRUSTED_HOSTS' => '^127\\.0\\.0\\.1$', 'TRUSTED_PROXIES' => '',
        'LOG_CHANNEL' => 'stderr', 'LOG_LEVEL' => 'warning', 'SYSTEM_ERROR_REPORTING_ENABLED' => 'false',
    ];
    // Remove inherited profile variables so Laravel reads this synthetic file.
    $environment = array_fill_keys(array_keys($profile), false);
    $environment['APP_ENV'] = 'production';
    foreach (['CONFIG' => 'config.php', 'ROUTES' => 'routes-v7.php', 'SERVICES' => 'services.php', 'PACKAGES' => 'packages.php', 'EVENTS' => 'events.php'] as $kind => $file) {
        $environment['APP_'.$kind.'_CACHE'] = $app.'/bootstrap/cache/'.$file;
    }
    file_put_contents($app.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'='.$value, array_keys($profile), $profile))."\n");
    foreach (['package:discover', 'config:cache', 'route:cache', 'view:cache'] as $command) {
        $process = new Process([PHP_BINARY, 'artisan', $command, '--no-ansi'], $app, $environment, timeout: 90);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Unpacked production release failed '.$command.'.');
        }
    }
    $preflight = new Process([PHP_BINARY, 'artisan', 'casa:check-hosting', '--no-ansi'], $app, $environment, timeout: 30);
    $preflight->run();
    if ($preflight->getExitCode() !== 1 || ! str_contains($preflight->getOutput(), '[FAIL] Application database checks completed')
        || str_contains($preflight->getOutput().$preflight->getErrorOutput(), 'package-smoke-secret')
        || str_contains($preflight->getOutput().$preflight->getErrorOutput(), 'SQLSTATE')) {
        throw new RuntimeException('Unpacked preflight did not safely reject the incomplete synthetic hosting configuration.');
    }
    file_put_contents($directory.'/router.php', '<?php $path = rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)); if (!in_array("..", explode("/", $path), true) && is_file(__DIR__."/public_html".$path)) { return false; } require __DIR__."/public_html/index.php";');
    $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $directory.'/public_html', $directory.'/router.php'], $app, $environment, timeout: 60);
    $server->start();
    $deadline = microtime(true) + 10;
    do {
        $connection = @stream_socket_client('tcp://127.0.0.1:'.$port, $error, $message, 0.1);
        if ($connection) {
            fclose($connection);
            break;
        }
        usleep(25000);
    } while ($server->isRunning() && microtime(true) < $deadline);
    if (! $server->isRunning()) {
        throw new RuntimeException('Unpacked release HTTP server did not start.');
    }
    $manifest = json_decode(file_get_contents($app.'/public/build/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $paths = ['/up' => 200, '/storage/hostinger-probe.txt' => 200, '/.env' => 404, '/composer.json' => 404, '/.git/config' => 404, '/storage/logs/laravel.log' => 404];
    foreach ($manifest as $entry) {
        $paths['/build/'.$entry['file']] = 200;
    }
    foreach ($paths as $path => $status) {
        $body = @file_get_contents('http://127.0.0.1:'.$port.$path, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]));
        $response = $http_response_header[0] ?? '';
        if ($body === false || ! preg_match('/\s'.$status.'\s/', $response) || str_contains($body, 'package-smoke-secret')) {
            throw new RuntimeException('Unpacked HTTP verification failed for '.$path.' (expected '.$status.').');
        }
        if ($path === '/storage/hostinger-probe.txt' && $body !== "hostinger storage probe\n") {
            throw new RuntimeException('Public upload link did not serve the expected fixture.');
        }
    }
    echo 'Archive smoke checks passed: checksum, production vendor, cached CLI boot, safe preflight failure, HTTP health/assets/uploads and private-file denial.'.PHP_EOL;
} finally {
    if ($server?->isRunning()) {
        $server->stop(1);
    }
    if (str_starts_with((string) realpath($directory), realpath(sys_get_temp_dir()).'/casa-hostinger-smoke-') && ! is_link($directory)) {
        (new Filesystem)->deleteDirectory($directory);
    }
}
