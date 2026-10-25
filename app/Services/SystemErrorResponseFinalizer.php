<?php

namespace App\Services;

use App\Exceptions\FeatureUnavailableException;
use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SystemErrorResponseFinalizer
{
    public function __construct(private readonly SystemErrorRecorder $recorder) {}

    public function finalize(Request $request, Response $response, ?Throwable $exception = null): Response
    {
        $requestId = $this->requestId($request);
        $response->headers->set('X-Request-ID', $requestId);

        if ($response->getStatusCode() < 400 || $response->getStatusCode() > 599) {
            return $response;
        }

        $this->recorder->record($request, $response, $exception);

        if ($request->attributes->get('casa_rendered_error_response') === $response) {
            return $response;
        }
        if (! $this->shouldRenderPage($request, $response, $exception)) {
            return $response;
        }

        try {
            $status = $response->getStatusCode();
            $content = view('errors.status', [
                'status' => $status,
                'reference' => $requestId,
                'content' => $this->content($status, $exception),
            ])->render();

            $response->setContent($content);
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Turbo-Visit-Control', 'reload');

            AddSecurityHeaders::apply($request, $response);
            $request->attributes->set('casa_rendered_error_response', $response);
        } catch (Throwable) {
            // Keep Laravel's original response if the error presentation itself cannot render.
        }

        return $response;
    }

    private function requestId(Request $request): string
    {
        $requestId = (string) $request->attributes->get('request_id', '');

        if (preg_match('/^[0-9a-f-]{36}$/i', $requestId) !== 1) {
            $requestId = (string) Str::uuid();
            $request->attributes->set('request_id', $requestId);
        }

        Context::add('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        return $requestId;
    }

    private function shouldRenderPage(Request $request, Response $response, ?Throwable $exception): bool
    {
        if ($request->expectsJson() || $response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        if (str_contains(strtolower((string) $response->headers->get('Content-Type')), 'json')) {
            return false;
        }

        if ($exception instanceof FeatureUnavailableException) {
            return true;
        }

        return ! (config('app.debug') && $response->getStatusCode() >= 500);
    }

    private function content(int $status, ?Throwable $exception): array
    {
        if ($exception instanceof FeatureUnavailableException) {
            return [
                'eyebrow' => 'Feature unavailable',
                'title' => $exception->featureName.' is still being prepared.',
                'message' => 'Please use another part of Casa Paraiso for now, then try again later.',
            ];
        }

        return match ($status) {
            400 => ['eyebrow' => 'Request problem', 'title' => 'We could not understand that request.', 'message' => 'Please go back and try again.'],
            401 => ['eyebrow' => 'Sign in required', 'title' => 'Please sign in to continue.', 'message' => 'Your session may have ended.'],
            403 => ['eyebrow' => 'Access restricted', 'title' => 'You do not have access to this page.', 'message' => 'If this seems unexpected, please contact an administrator.'],
            404 => ['eyebrow' => 'Page not found', 'title' => 'We could not find that page.', 'message' => 'The page may have moved or no longer be available.'],
            419 => ['eyebrow' => 'Session expired', 'title' => 'Your form session has expired.', 'message' => 'Please go back, refresh the page, and submit the form again.'],
            422 => ['eyebrow' => 'Request could not be completed', 'title' => 'Please review the information and try again.', 'message' => 'Some details may need to be corrected.'],
            429 => ['eyebrow' => 'Please slow down', 'title' => 'Too many requests were sent.', 'message' => 'Wait a moment before trying again.'],
            500 => ['eyebrow' => 'Something went wrong', 'title' => 'We could not complete that request.', 'message' => 'Please try again shortly.'],
            503 => ['eyebrow' => 'Temporarily unavailable', 'title' => 'This service is not available right now.', 'message' => 'Please try again later.'],
            default => $status >= 500
                ? ['eyebrow' => 'Something went wrong', 'title' => 'We could not complete that request.', 'message' => 'Please try again shortly.']
                : ['eyebrow' => 'Request problem', 'title' => 'We could not complete that request.', 'message' => 'Please go back and try again.'],
        };
    }
}
