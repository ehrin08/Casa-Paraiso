<?php

// CLI-only release builder. Does not bootstrap the local application or read .env.
use Composer\InstalledVersions;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';
$root = realpath(dirname(__DIR__));
$stage = sys_get_temp_dir().'/casa-hostinger-'.bin2hex(random_bytes(8));
$app = $stage.'/casa-paraiso';
$archivePath = null;
$zipOpen = false;
$verified = false;

function copyReleaseTree(string $source, string $destination): void
{
    if (is_link($source)) {
        throw new RuntimeException('Release sources must not be symbolic links.');
    }
    if (is_dir($source)) {
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        foreach (new DirectoryIterator($source) as $entry) {
            if (! $entry->isDot()) {
                copyReleaseTree($entry->getPathname(), $destination.'/'.$entry->getFilename());
            }
        }
    } elseif (is_file($source)) {
        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }
        if (! copy($source, $destination)) {
            throw new RuntimeException('Unable to copy a release source.');
        }
    } else {
        throw new RuntimeException('A required release source is missing.');
    }
}

function releaseCommand(array $command, string $directory): void
{
    $process = new Process($command, $directory, timeout: 300);
    $process->run(function ($type, $buffer): void {
        echo $buffer;
    });
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Staged production dependency verification failed.');
    }
}

function removeReleaseStage(string $directory): void
{
    $temporaryRoot = realpath(sys_get_temp_dir());
    $resolved = realpath($directory);
    if ($resolved === false || ! str_starts_with($resolved, $temporaryRoot.'/casa-hostinger-') || is_link($directory)) {
        throw new RuntimeException('Refusing to clean an unexpected staging path.');
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && ! $entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

mkdir($stage, 0700);
try {
    // Explicit runtime allowlist: never copy the checkout, storage or vendor wholesale.
    foreach (['app', 'config', 'routes', 'database/migrations', 'resources/views'] as $path) {
        copyReleaseTree($root.'/'.$path, $app.'/'.$path);
    }
    foreach (['artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'bootstrap/providers.php', 'resources/sentiment/model-v1.json',
        'public/.htaccess', 'public/index.php', 'public/favicon.ico', 'public/robots.txt', 'public/images', 'public/build'] as $path) {
        copyReleaseTree($root.'/'.$path, $app.'/'.$path);
    }
    copyReleaseTree($root.'/deploy/hostinger.env.example', $app.'/.env.example');
    copyReleaseTree($root.'/docs/HOSTINGER_DEPLOYMENT.md', $stage.'/HOSTINGER_DEPLOYMENT.md');
    foreach (['bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $path) {
        if (! is_dir($app.'/'.$path)) {
            mkdir($app.'/'.$path, 0755, true);
        }
    }

    releaseCommand(['composer', 'install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction', '--no-ansi'], $app);
    releaseCommand(['composer', 'check-platform-reqs', '--no-dev', '--no-ansi'], $app);
    releaseCommand(['composer', 'audit', '--locked', '--no-dev', '--no-ansi'], $app);
    $installed = json_decode(file_get_contents($app.'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    if (($installed['dev'] ?? true) !== false || is_file($app.'/.env')) {
        throw new RuntimeException('Staged release contains development dependencies or an environment.');
    }
    foreach (glob($app.'/bootstrap/cache/*.php') as $cache) {
        unlink($cache);
    }
    file_put_contents($stage.'/RELEASE.json', json_encode([
        'built_at_utc' => gmdate('c'), 'php' => PHP_VERSION,
        'laravel' => InstalledVersions::getPrettyVersion('laravel/framework'),
        'composer_lock_sha256' => hash_file('sha256', $root.'/composer.lock'),
        'package_lock_sha256' => hash_file('sha256', $root.'/package-lock.json'),
        'includes_credentials_or_business_data' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $output = $root.'/output/hostinger-release';
    if (! is_dir($output)) {
        mkdir($output, 0755, true);
    }
    if (! str_starts_with(realpath($output), $root.DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Release output must stay within this workspace.');
    }
    $filename = 'casa-paraiso-hostinger-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.zip';
    $archivePath = $output.'/'.$filename;
    $zip = new ZipArchive;
    if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException('Unable to create the deployment archive.');
    }
    $zipOpen = true;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $entry) {
        $name = substr($entry->getPathname(), strlen($stage) + 1);
        if ($entry->isLink() || preg_match('~(?:^|/)(?:\.env(?!\.example(?:/|$))[^/]*|auth\.json|\.git|node_modules|tests|output)(?:/|$)~', $name)
            || preg_match('~\.(?:sql|sqlite|log)$~i', $name)) {
            throw new RuntimeException('Unexpected sensitive or development file in release staging.');
        }
        if ($entry->isDir()) {
            $name .= '/';
            $zip->addEmptyDir($name);
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 040755 << 16);
        } else {
            $zip->addFile($entry->getPathname(), $name);
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16);
        }
    }
    if (! $zip->close()) {
        throw new RuntimeException('Unable to finalize the deployment archive.');
    }
    $zipOpen = false;
    $checksum = hash_file('sha256', $archivePath);
    file_put_contents($archivePath.'.sha256', $checksum.'  '.$filename.PHP_EOL);
    releaseCommand([PHP_BINARY, $root.'/deploy/verify-hostinger.php', $archivePath], $root);
    $verified = true;
    echo 'Release: output/hostinger-release/'.$filename.PHP_EOL;
    echo 'SHA-256: '.$checksum.PHP_EOL;
    echo 'Existing database, uploaded files and APP_KEY require the separate private transfer in the deployment guide.'.PHP_EOL;
} finally {
    if ($zipOpen) {
        $zip->close();
    }
    if (! $verified && $archivePath !== null) {
        foreach ([$archivePath, $archivePath.'.sha256'] as $incomplete) {
            if (is_file($incomplete)) {
                unlink($incomplete);
            }
        }
    }
    removeReleaseStage($stage);
}
