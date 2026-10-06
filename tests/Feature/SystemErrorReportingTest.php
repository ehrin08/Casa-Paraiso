<?php

namespace Tests\Feature;

use App\Exceptions\FeatureUnavailableException;
use App\Models\SystemErrorOccurrence;
use App\Models\SystemErrorReport;
use App\Models\User;
use App\Services\SystemErrorRetention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SystemErrorReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_server_failures_show_a_safe_branded_page_and_record_a_reference(): void
    {
        config(['app.debug' => false]);
        Route::get('/_system-error-test/boom', function (): void {
            throw new RuntimeException('password=do-not-show-this');
        });

        $response = $this->get('/_system-error-test/boom')
            ->assertStatus(500)
            ->assertHeader('X-Request-ID')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('We could not complete that request.')
            ->assertDontSee('do-not-show-this');

        $report = SystemErrorReport::firstOrFail();
        $occurrence = SystemErrorOccurrence::firstOrFail();

        $this->assertSame(SystemErrorReport::CATEGORY_SERVER_ERROR, $report->category);
        $this->assertSame(1, $report->occurrence_count);
        $this->assertSame($response->headers->get('X-Request-ID'), $occurrence->request_id);
        $this->assertStringNotContainsString('do-not-show-this', $report->message);
        $this->assertStringNotContainsString('do-not-show-this', (string) DB::table('system_error_reports')->value('message'));
    }

    public function test_404_responses_are_grouped_and_reopen_after_resolution(): void
    {
        config(['app.debug' => false]);

        $this->get('/_system-error-test/missing')->assertNotFound()->assertSee('We could not find that page.');
        $this->get('/_system-error-test/missing')->assertNotFound();

        $report = SystemErrorReport::firstOrFail();
        $this->assertSame(2, $report->occurrence_count);
        $this->assertSame(2, SystemErrorOccurrence::count());

        $report->forceFill(['resolved_at' => now(), 'resolved_by' => User::factory()->admin()->create()->id])->save();

        $this->get('/_system-error-test/missing')->assertNotFound();

        $report->refresh();
        $this->assertSame(3, $report->occurrence_count);
        $this->assertNull($report->resolved_at);
    }

    public function test_direct_json_errors_keep_their_json_response_and_are_recorded(): void
    {
        Route::get('/_system-error-test/json', function () {
            return response()->json(['message' => 'Invalid JSON workflow.'], 422);
        });

        $response = $this->getJson('/_system-error-test/json')
            ->assertStatus(422)
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('message', 'Invalid JSON workflow.');

        $this->assertSame(422, SystemErrorReport::firstOrFail()->http_status);
        $this->assertSame($response->headers->get('X-Request-ID'), SystemErrorOccurrence::firstOrFail()->request_id);
    }

    public function test_common_http_error_statuses_render_safe_pages_and_references(): void
    {
        config(['app.debug' => false]);

        foreach ([403, 419, 429] as $status) {
            Route::get("/_system-error-test/status-{$status}", function () use ($status): void {
                abort($status);
            });

            $this->get("/_system-error-test/status-{$status}")
                ->assertStatus($status)
                ->assertHeader('X-Request-ID')
                ->assertSee('Reference:');
        }

        $this->assertDatabaseCount('system_error_reports', 3);
    }

    public function test_validation_redirects_keep_existing_inline_validation_behavior(): void
    {
        Route::post('/_system-error-test/validation', function (Request $request): void {
            $request->validate(['name' => ['required']]);
        });

        $this->post('/_system-error-test/validation', [])
            ->assertRedirect()
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('system_error_reports', 0);
    }

    public function test_feature_unavailable_exception_uses_the_safe_503_display_and_category(): void
    {
        Route::get('/_system-error-test/unavailable', function (): void {
            throw FeatureUnavailableException::for('Rewards export');
        });

        $this->get('/_system-error-test/unavailable')
            ->assertStatus(503)
            ->assertSee('Rewards export is still being prepared.');

        $this->assertSame(SystemErrorReport::CATEGORY_FEATURE_UNAVAILABLE, SystemErrorReport::firstOrFail()->category);
    }

    public function test_only_the_super_administrator_can_open_the_error_inbox_and_manage_resolution(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->create([
            'email' => config('auth.super_admin_email'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $report = SystemErrorReport::create([
            'reference' => (string) str()->uuid(),
            'fingerprint' => hash('sha256', 'system-error-access-test'),
            'http_status' => 500,
            'category' => SystemErrorReport::CATEGORY_SERVER_ERROR,
            'exception_class' => RuntimeException::class,
            'message' => 'Safe test message',
            'stack_frames' => [],
            'occurrence_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.system-errors.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('admin.system-errors.index'))->assertOk()->assertSee('System issues');
        $this->actingAs($superAdmin)->get(route('admin.dashboard'))->assertOk()->assertSee('1 unresolved server errors');
        $this->actingAs($superAdmin)->patch(route('admin.system-errors.resolve', $report))->assertRedirect();
        $this->assertNotNull($report->fresh()->resolved_at);
        $this->actingAs($superAdmin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('1 unresolved server errors');
        $this->actingAs($superAdmin)->patch(route('admin.system-errors.reopen', $report))->assertRedirect();
        $this->assertNull($report->fresh()->resolved_at);
    }

    public function test_retention_prunes_old_occurrences_and_resolved_empty_reports(): void
    {
        $report = SystemErrorReport::create([
            'reference' => (string) str()->uuid(),
            'fingerprint' => hash('sha256', 'system-error-retention-test'),
            'http_status' => 404,
            'category' => SystemErrorReport::CATEGORY_CLIENT_ERROR,
            'exception_class' => 'HTTP 404',
            'message' => 'Not found',
            'stack_frames' => [],
            'occurrence_count' => 1,
            'first_seen_at' => now()->subDays(31),
            'last_seen_at' => now()->subDays(31),
            'resolved_at' => now()->subDays(31),
        ]);
        SystemErrorOccurrence::create([
            'system_error_report_id' => $report->id,
            'request_id' => (string) str()->uuid(),
            'request_method' => 'GET',
            'request_path' => '/expired',
            'occurred_at' => now()->subDays(31),
        ]);

        app(SystemErrorRetention::class)->prune();

        $this->assertDatabaseCount('system_error_occurrences', 0);
        $this->assertDatabaseCount('system_error_reports', 0);
    }
}
