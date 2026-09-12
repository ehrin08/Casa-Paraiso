<?php

use App\Services\SystemErrorRetention;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(SystemErrorRetention::class)->prune())
    ->daily()
    ->name('system-error-retention-prune')
    ->withoutOverlapping();
