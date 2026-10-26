<?php

namespace App\Console\Commands;

use App\Services\HostingReadiness;
use Illuminate\Console\Command;

class CheckHosting extends Command
{
    protected $signature = 'casa:check-hosting';

    protected $description = 'Check production hosting prerequisites without changing data or sending messages.';

    public function handle(HostingReadiness $readiness): int
    {
        $result = $readiness->inspect();
        foreach ($result['checks'] as $label => $passed) {
            $this->line(($passed ? '[OK] ' : '[FAIL] ').$label);
        }
        if ($result['database_version'] !== null) {
            $this->line('Database version: '.substr($result['database_version'], 0, 100));
        }
        $this->line('Read-only: no records, settings, files or messages were changed.');
        $this->line('Also verify host PHP/CLI parity, database-version compatibility, HTTPS redirects, both Google callbacks, email delivery, cron and backup recovery.');
        if (in_array(false, $result['checks'], true)) {
            $this->error('Hosting checks failed. Follow docs/HOSTINGER_DEPLOYMENT.md.');

            return self::FAILURE;
        }
        $this->info('Automated hosting checks passed. Complete the documented live hosting checks before opening bookings.');

        return self::SUCCESS;
    }
}
