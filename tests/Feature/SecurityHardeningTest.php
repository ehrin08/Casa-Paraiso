<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_responses_include_the_security_header_baseline(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
    }

    public function test_hsts_is_only_emitted_for_secure_requests_when_enabled(): void
    {
        config(['casa.security.hsts' => false]);

        $this->get('/')
            ->assertHeaderMissing('Strict-Transport-Security');

        config(['casa.security.hsts' => true]);

        $this->get('https://localhost/')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_sensitive_routes_use_named_rate_limiters(): void
    {
        $this->assertRouteUsesMiddleware('POST', 'register', 'throttle:guest-sensitive');
        $this->assertRouteUsesMiddleware('POST', 'forgot-password', 'throttle:guest-sensitive');
        $this->assertRouteUsesMiddleware('POST', 'reset-password', 'throttle:guest-sensitive');
        $this->assertRouteUsesMiddleware('DELETE', 'profile', 'throttle:user-sensitive');
        $this->assertRouteUsesMiddleware('PUT', 'password', 'throttle:user-sensitive');
    }

    private function assertRouteUsesMiddleware(string $method, string $uri, string $middleware): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri,
        );

        $this->assertNotNull($route, "Route {$method} {$uri} was not registered.");
        $this->assertContains($middleware, $route->gatherMiddleware());
    }
}
