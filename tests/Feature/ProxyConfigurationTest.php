<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProxyConfigurationTest extends TestCase
{
    public function test_dotenv_proxies_work_on_cached_and_uncached_http_boots(): void
    {
        foreach ([false, true] as $cached) {
            $result = $this->probe('198.51.100.10', $cached);
            $this->assertSame($cached, $result['cached']);
            $this->assertSame(['ip' => '203.0.113.77', 'secure' => true], $result['clients']['198.51.100.10']);
            $this->assertSame(['ip' => '203.0.113.9', 'secure' => false], $result['clients']['203.0.113.9']);
        }
    }

    public function test_empty_production_proxy_list_ignores_forwarded_headers(): void
    {
        foreach ([false, true] as $cached) {
            $result = $this->probe('', $cached);
            $this->assertSame(['ip' => '198.51.100.10', 'secure' => false], $result['clients']['198.51.100.10']);
        }
    }

    private function probe(string $proxies, bool $cached): array
    {
        $directory = sys_get_temp_dir().'/casa-proxy-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        file_put_contents($directory.'/.env', "APP_ENV=production\nAPP_DEBUG=false\nAPP_URL=https://spa.example\nCACHE_STORE=array\nTRUSTED_PROXIES={$proxies}\n");
        try {
            if ($cached) {
                $this->runProbe($directory, 'cache');
            }

            return json_decode($this->runProbe($directory, 'probe'), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function runProbe(string $directory, string $mode): string
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/proxy-bootstrap.php'), $directory, $mode], base_path(), timeout: 45);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return $process->getOutput();
    }
}
