<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\MonitorErrorResponses;
use App\Http\Middleware\RestorePanelForm;
use App\Services\SystemErrorResponseFinalizer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(MonitorErrorResponses::class);
        $middleware->append(AddSecurityHeaders::class);
        $middleware->web(append: [RestorePanelForm::class]);

        $middleware->trustHosts(
            at: fn (): array => config('casa.security.trusted_hosts', []),
            subdomains: false,
        );

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => EnsureUserHasRole::class,
            'super_admin' => EnsureUserIsSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            return app(SystemErrorResponseFinalizer::class)->finalize($request, $response, $exception);
        });
    })->create();
