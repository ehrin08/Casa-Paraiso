<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureApplicationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $queryCount = 0;
        $queryMilliseconds = 0.0;
        $requestId = (string) $request->attributes->get('request_id');

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryMilliseconds): void {
            $queryCount++;
            $queryMilliseconds += $query->time;
        });

        $response = $next($request);
        $durationMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;

        Log::log($durationMilliseconds >= 1000 ? 'warning' : 'info', 'http_request', [
            'request_id' => $requestId,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round($durationMilliseconds, 1),
            'db_queries' => $queryCount,
            'db_duration_ms' => round($queryMilliseconds, 1),
        ]);

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('Server-Timing', sprintf('app;dur=%.1f, db;dur=%.1f, queries;desc="%d"', $durationMilliseconds, $queryMilliseconds, $queryCount));

        return $response;
    }
}
