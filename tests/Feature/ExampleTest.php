<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_fast_navigation_asset_defines_turbo_lifecycle_and_exclusions(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('turboSession.drive = false', $script);
        $this->assertStringContainsString("'turbo:before-render'", $script);
        $this->assertStringContainsString("'turbo:visit'", $script);
        $this->assertStringContainsString("'turbo:load'", $script);
        $this->assertStringContainsString("'turbo:before-cache'", $script);
        $this->assertStringContainsString("link.hasAttribute('data-panel-link')", $script);
        $this->assertStringContainsString("url.pathname.includes('/export')", $script);
        $this->assertStringContainsString("form.method.toLowerCase() !== 'get'", $script);
        $this->assertStringContainsString('const panelCache = new Map()', $script);
        $this->assertStringContainsString('const panelCacheTtl = 60000', $script);
        $this->assertStringContainsString('panelCache.clear()', $script);
        $this->assertStringNotContainsString("prefetch.rel = 'prefetch'", $script);
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('data-page-loading', false)
            ->assertSee('data-turbo-track="reload"', false)
            ->assertDontSee('data-prefetch', false)
            ->assertSee('Treatments made for your return to yourself.')
            ->assertSee('1:00 PM to 12:00 MN')
            ->assertSee('Reserve your spot. You deserve this.');
    }

    public function test_landing_page_uses_fixed_navigation_and_screen_fit_sections(): void
    {
        $response = $this->get('/')->assertOk();
        $html = $response->getContent();
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($html);
        $this->assertIsString($css);
        $this->assertStringContainsString('data-landing-header="fixed"', $html);
        $this->assertStringContainsString('class="casa-landing-main"', $html);
        $this->assertSame(4, substr_count($html, 'data-landing-screen-section'));
        $this->assertStringContainsString('id="treatments"', $html);
        $this->assertStringContainsString('id="how-it-works"', $html);
        $this->assertStringContainsString('id="visit"', $html);
        $this->assertStringContainsString('.casa-landing-header', $css);
        $this->assertStringContainsString('min-block-size: calc(100dvh - var(--casa-landing-header-height));', $css);
        $this->assertStringContainsString('scroll-margin-block-start: var(--casa-landing-header-height);', $css);
    }

    public function test_guest_entry_points_use_login_without_signup_promotion(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('aria-label="Account navigation"', false)
            ->assertSee('href="'.route('login').'" class="casa-button-primary">Login</a>', false)
            ->assertDontSee('>Reserve</a>', false);

        $login = $this->get('/login');

        $login
            ->assertOk()
            ->assertSee('data-guest-viewport="desktop-fixed"', false)
            ->assertSee('data-guest-scroll-region="true"', false)
            ->assertSee('name="password"', false)
            ->assertSee('Continue with Google')
            ->assertSee('data-login-instructions', false)
            ->assertSee('Sign-in instructions')
            ->assertSee('Guests')
            ->assertSee('Team')
            ->assertSee('Sign in with your email and password, or continue with your verified Google account.')
            ->assertDontSee('Create an account')
            ->assertDontSee('New customer?')
            ->assertDontSee('href="'.route('register').'"', false);

        $loginHtml = $login->getContent();

        $this->assertIsString($loginHtml);
        $this->assertMatchesRegularExpression('/<details\b(?=[^>]*\bdata-login-instructions\b)(?![^>]*\bopen\b)[^>]*>/', $loginHtml);

        $this->get('/register')
            ->assertOk()
            ->assertSee('data-guest-viewport="fluid"', false)
            ->assertSee('data-guest-scroll-region="false"', false)
            ->assertDontSee('data-login-instructions', false);
    }
}
