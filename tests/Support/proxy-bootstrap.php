<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$directory = $argv[1];
foreach (['APP_ENV' => 'production', 'APP_CONFIG_CACHE' => $directory.'/config.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
putenv('TRUSTED_PROXIES');
unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($directory);

if (($argv[2] ?? '') === 'cache') {
    $app->make(ConsoleKernel::class)->bootstrap();
    file_put_contents($directory.'/config.php', '<?php return '.var_export($app->make('config')->all(), true).';');
    exit;
}

$app->make(HttpKernel::class)->bootstrap();
$clients = [];
foreach (['198.51.100.10', '203.0.113.9'] as $remote) {
    $request = Request::create('http://spa.example/up', server: [
        'REMOTE_ADDR' => $remote,
        'HTTP_X_FORWARDED_FOR' => '203.0.113.77',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);
    $app->make(TrustProxies::class)->handle($request, fn () => new Response);
    $clients[$remote] = ['ip' => $request->ip(), 'secure' => $request->isSecure()];
}
echo json_encode(['cached' => $app->configurationIsCached(), 'clients' => $clients], JSON_THROW_ON_ERROR);
