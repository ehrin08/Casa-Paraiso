<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a failed form-panel submission's errors and input for one more request,
 * so the list page can reopen the same panel and the panel request can display them.
 */
class RestorePanelForm
{
    public const ATTRIBUTE = 'casa.panel_restore';

    /** Mirrors the panel loader's eligible prefixes in resources/js/app.js. */
    public const PREFIXES = [
        '/admin/addons/',
        '/admin/appointments/',
        '/reception/appointments/',
        '/reception/transactions/',
        '/admin/customers/',
        '/admin/staff/',
        '/admin/services/',
        '/admin/transactions/',
        '/admin/promotions/',
        '/admin/feedback/',
        '/staff/appointments/',
        '/staff/customers/',
        '/staff/transactions/',
        '/staff/feedback/',
        '/customer/appointments/',
        '/customer/feedback/',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->hasHeader('X-Casa-Panel') && ! $request->expectsJson() && $request->hasSession()) {
            $session = $request->session();
            $path = $session->getOldInput('_panel');

            if ($session->has('errors') && self::isSafePath($path)) {
                $session->reflash();
                $request->attributes->set(self::ATTRIBUTE, $path);
            }
        }

        return $next($request);
    }

    public static function isSafePath(mixed $path): bool
    {
        if (! is_string($path) || strlen($path) > 512 || preg_match('#^/[A-Za-z0-9/_\-]+(\?[A-Za-z0-9_\-=&%.:+\[\]]*)?$#', $path) !== 1) {
            return false;
        }

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
