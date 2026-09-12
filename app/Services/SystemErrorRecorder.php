<?php

namespace App\Services;

use App\Exceptions\FeatureUnavailableException;
use App\Models\SystemErrorOccurrence;
use App\Models\SystemErrorReport;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SystemErrorRecorder
{
    public function __construct(private readonly SystemErrorRetention $retention) {}

    public function record(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $status = $response->getStatusCode();

        if (! config('casa.error_reporting.enabled', true) || $status < 400 || $status > 599 || $request->attributes->get('system_error_captured')) {
            return;
        }

        $request->attributes->set('system_error_captured', true);
        $requestId = $this->requestId($request);
        $details = $this->details($request, $status, $exception);

        try {
            DB::transaction(function () use ($details, $request, $requestId): void {
                $report = SystemErrorReport::query()
                    ->where('fingerprint', $details['fingerprint'])
                    ->lockForUpdate()
                    ->first();

                if ($report === null) {
                    try {
                        $report = SystemErrorReport::create([
                            ...$details,
                            'reference' => (string) Str::uuid(),
                            'occurrence_count' => 0,
                            'first_seen_at' => now(),
                            'last_seen_at' => now(),
                        ]);
                    } catch (QueryException) {
                        $report = SystemErrorReport::query()
                            ->where('fingerprint', $details['fingerprint'])
                            ->lockForUpdate()
                            ->firstOrFail();
                    }
                }

                $userId = $this->userId($request);

                $report->forceFill([
                    ...$details,
                    'occurrence_count' => $report->occurrence_count + 1,
                    'last_request_id' => $requestId,
                    'last_route_name' => $details['last_route_name'],
                    'last_request_method' => $details['last_request_method'],
                    'last_request_path' => $details['last_request_path'],
                    'last_user_id' => $userId,
                    'last_seen_at' => now(),
                    'resolved_by' => null,
                    'resolved_at' => null,
                ])->save();

                SystemErrorOccurrence::create([
                    'system_error_report_id' => $report->id,
                    'request_id' => $requestId,
                    'route_name' => $details['last_route_name'],
                    'request_method' => $details['last_request_method'],
                    'request_path' => $details['last_request_path'],
                    'user_id' => $userId,
                    'occurred_at' => now(),
                ]);
            }, attempts: 3);

            Cache::forget('system-error-reports:unresolved-server-count');
            $this->retention->scheduleFallbackPrune();
        } catch (Throwable) {
            $this->fallbackLog($request, $status, $exception, $requestId);
        }
    }

    private function details(Request $request, int $status, ?Throwable $exception): array
    {
        [$sourceFile, $sourceLine, $stackFrames] = $this->sourceDetails($exception);
        $routeName = $request->route()?->getName();
        $exceptionClass = $exception ? $exception::class : 'HTTP '.$status;
        $category = $exception instanceof FeatureUnavailableException
            ? SystemErrorReport::CATEGORY_FEATURE_UNAVAILABLE
            : ($status >= 500 ? SystemErrorReport::CATEGORY_SERVER_ERROR : SystemErrorReport::CATEGORY_CLIENT_ERROR);

        return [
            'fingerprint' => hash('sha256', implode('|', [
                $status,
                $category,
                $exceptionClass,
                $sourceFile,
                $sourceLine,
                $routeName,
            ])),
            'http_status' => $status,
            'category' => $category,
            'exception_class' => $exceptionClass,
            'message' => $this->safeMessage($exception, $status),
            'source_file' => $sourceFile,
            'source_line' => $sourceLine,
            'stack_frames' => $stackFrames,
            'last_route_name' => $routeName,
            'last_request_method' => strtoupper($request->method()),
            'last_request_path' => '/'.ltrim($request->path(), '/'),
        ];
    }

    private function sourceDetails(?Throwable $exception): array
    {
        if ($exception === null) {
            return [null, null, []];
        }

        $frames = array_merge([[
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => null,
            'function' => null,
        ]], $exception->getTrace());

        $normalized = collect($frames)
            ->filter(fn (array $frame): bool => isset($frame['file']))
            ->map(function (array $frame): array {
                return [
                    'file' => $this->relativePath((string) $frame['file']),
                    'line' => isset($frame['line']) ? (int) $frame['line'] : null,
                    'class' => isset($frame['class']) ? Str::limit((string) $frame['class'], 255, '') : null,
                    'function' => isset($frame['function']) ? Str::limit((string) $frame['function'], 255, '') : null,
                ];
            })
            ->take(25)
            ->values();

        $applicationFrame = $normalized->first(fn (array $frame): bool => str_starts_with((string) $frame['file'], 'app/'))
            ?? $normalized->first();

        return [
            $applicationFrame['file'] ?? null,
            $applicationFrame['line'] ?? null,
            $normalized->all(),
        ];
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalizedPath = str_replace('\\', '/', $path);

        return str_starts_with($normalizedPath, $basePath)
            ? substr($normalizedPath, strlen($basePath))
            : basename($normalizedPath);
    }

    private function safeMessage(?Throwable $exception, int $status): string
    {
        if ($exception instanceof FeatureUnavailableException) {
            return 'This feature is not available yet.';
        }

        if ($exception instanceof QueryException) {
            $sqlState = $exception->errorInfo[0] ?? null;

            return $sqlState ? "Database query failed (SQLSTATE {$sqlState})." : 'Database query failed.';
        }

        if ($exception === null) {
            return Response::$statusTexts[$status] ?? 'HTTP error response.';
        }

        $message = strip_tags($exception->getMessage());
        $message = preg_replace('/(password|secret|token|api[_-]?key|authorization)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $message) ?? '';
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [redacted]', $message) ?? '';

        return Str::limit(trim($message) ?: class_basename($exception), 2000, '…');
    }

    private function requestId(Request $request): string
    {
        $requestId = (string) $request->attributes->get('request_id', '');

        if (preg_match('/^[0-9a-f-]{36}$/i', $requestId) === 1) {
            return $requestId;
        }

        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        return $requestId;
    }

    private function userId(Request $request): ?int
    {
        try {
            return $request->user()?->getAuthIdentifier();
        } catch (Throwable) {
            return null;
        }
    }

    private function fallbackLog(Request $request, int $status, ?Throwable $exception, string $requestId): void
    {
        try {
            Log::warning('system_error_capture_failed', [
                'request_id' => $requestId,
                'status' => $status,
                'exception_class' => $exception ? $exception::class : null,
                'route' => $request->route()?->getName(),
            ]);
        } catch (Throwable) {
            // The original response remains the only reliable fallback.
        }
    }
}
