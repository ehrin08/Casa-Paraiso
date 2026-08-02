<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production') && config('casa.security.force_https')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('guest-sensitive', function (Request $request): array {
            $key = strtolower(trim((string) $request->input('email', 'anonymous'))).'|'.$request->ip();

            return [
                Limit::perMinute(10)->by('minute:'.$key),
                Limit::perHour(30)->by('hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('user-sensitive', fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        Paginator::defaultView('pagination.compact');

        Vite::useScriptTagAttributes([
            'data-turbo-track' => 'reload',
        ]);

        Vite::useStyleTagAttributes([
            'data-turbo-track' => 'reload',
        ]);
    }
}
