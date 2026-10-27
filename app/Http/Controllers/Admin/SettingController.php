<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplicationSettingRequest;
use App\Models\ApplicationSetting;
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

        ApplicationSetting::updateCurrent([...$request->validated(), 'updated_by' => $request->user()->id]);

        return back()->with('status', 'settings-updated');
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
