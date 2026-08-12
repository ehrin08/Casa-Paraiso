<?php

namespace App\Services;

use App\Models\SystemErrorOccurrence;
use App\Models\SystemErrorReport;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SystemErrorRetention
{
    public function prune(): void
    {
        $cutoff = now()->subDays((int) config('casa.error_reporting.retention_days', 30));

        SystemErrorOccurrence::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        SystemErrorReport::query()
            ->whereNotNull('resolved_at')
            ->where('last_seen_at', '<', $cutoff)
            ->doesntHave('occurrences')
            ->delete();
    }

    public function scheduleFallbackPrune(): void
    {
        try {
            if (! Cache::add('system-error-retention:due', true, now()->addDay())) {
                return;
            }

            defer(function (): void {
                $this->prune();
            });
        } catch (Throwable) {
            // Error monitoring must never introduce a second application failure.
        }
    }
}
