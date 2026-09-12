<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplicationSettingRequest;
use App\Models\ApplicationSetting;
use App\Services\OrgChartPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => ApplicationSetting::current(),
            'settingsTableAvailable' => ApplicationSetting::tableAvailable(),
            'securityChecks' => $this->securityChecks(),
        ]);
    }

    public function update(ApplicationSettingRequest $request): RedirectResponse
    {
        if (! ApplicationSetting::tableAvailable()) {
            throw ValidationException::withMessages([
                'settings' => __('The application settings migration must be applied before settings can be saved.'),
            ]);
        }

        $validated = collect($request->validated())
            ->except(['organization_chart_photos', 'organization_chart_remove_photos'])
            ->all();

        $settings = ApplicationSetting::updateCurrent([...$validated, 'updated_by' => $request->user()->id]);

        $this->processOrgChartPhotos($request, $settings);

        return back()->with('status', 'settings-updated');
    }

    private function processOrgChartPhotos(ApplicationSettingRequest $request, ApplicationSetting $settings): void
    {
        $photoService = app(OrgChartPhotoService::class);
        $chart = is_array($settings->organization_chart) ? $settings->organization_chart : [];
        $photos = $request->file('organization_chart_photos', []);
        $removals = $request->input('organization_chart_remove_photos', []);
        $changed = false;

        // Process owner & admin photo uploads and removals
        foreach (['owner', 'admin'] as $tier) {
            if ($request->boolean("organization_chart_remove_photos.{$tier}") && filled($chart[$tier]['photo'] ?? null)) {
                $this->deleteOrgChartPhoto($photoService, $chart[$tier]['photo']);
                $chart[$tier]['photo'] = null;
                $changed = true;
            }

            if (isset($photos[$tier]) && $photos[$tier]->isValid()) {
                // Delete old photo if replacing
                if (filled($chart[$tier]['photo'] ?? null)) {
                    $this->deleteOrgChartPhoto($photoService, $chart[$tier]['photo']);
                }
                $chart[$tier]['photo'] = $photoService->store($photos[$tier], $tier);
                $changed = true;
            }
        }

        // Process team photo uploads and removals
        $teamPhotos = $photos['team'] ?? [];
        $teamRemovals = $removals['team'] ?? [];

        foreach ($chart['team'] ?? [] as $index => $role) {
            if ((bool) ($teamRemovals[$index] ?? false) && filled($role['photo'] ?? null)) {
                $this->deleteOrgChartPhoto($photoService, $role['photo']);
                $chart['team'][$index]['photo'] = null;
                $changed = true;
            }

            if (isset($teamPhotos[$index]) && $teamPhotos[$index]->isValid()) {
                if (filled($role['photo'] ?? null)) {
                    $this->deleteOrgChartPhoto($photoService, $role['photo']);
                }
                $chart['team'][$index]['photo'] = $photoService->store($teamPhotos[$index], "team-{$index}");
                $changed = true;
            }
        }

        if ($changed) {
            $settings->update(['organization_chart' => $chart]);
        }
    }

    private function deleteOrgChartPhoto(OrgChartPhotoService $photoService, string $path): void
    {
        if (str_starts_with($path, 'org-chart/')) {
            $photoService->delete($path);
        }
    }

    /** @return array<int, array{label: string, value: string, meta: string, tone: string}> */
    private function securityChecks(): array
    {
        $production = app()->environment('production');
        $debugSafe = ! $production || ! config('app.debug');
        $secureCookie = (bool) config('session.secure');
        $encryptedSession = (bool) config('session.encrypt');
        $trustedHosts = config('casa.security.trusted_hosts', []);

        return [
            [
                'label' => __('Debug mode'),
                'value' => config('app.debug') ? __('Enabled') : __('Disabled'),
                'meta' => $debugSafe ? __('Safe for the current environment') : __('Disable APP_DEBUG before production'),
                'tone' => $debugSafe ? 'green' : 'gold',
            ],
            [
                'label' => __('HTTPS cookies'),
                'value' => $secureCookie ? __('Required') : __('Local default'),
                'meta' => $secureCookie ? __('Session cookies require HTTPS') : __('Enable SESSION_SECURE_COOKIE in production'),
                'tone' => $secureCookie ? 'green' : 'gold',
            ],
            [
                'label' => __('Session encryption'),
                'value' => $encryptedSession ? __('Enabled') : __('Disabled'),
                'meta' => $encryptedSession ? __('Stored session payloads are encrypted') : __('Enable SESSION_ENCRYPT in production'),
                'tone' => $encryptedSession ? 'green' : 'gold',
            ],
            [
                'label' => __('Trusted hosts'),
                'value' => $trustedHosts === [] ? __('Not restricted') : (string) count($trustedHosts),
                'meta' => $trustedHosts === [] ? __('Set TRUSTED_HOSTS for production') : __('Incoming host names are restricted'),
                'tone' => $trustedHosts === [] ? 'gold' : 'green',
            ],
        ];
    }
}
