<?php

namespace App\Http\Middleware;

use App\Services\SystemErrorResponseFinalizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MonitorErrorResponses
{
    public function __construct(private readonly SystemErrorResponseFinalizer $finalizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
        Context::add('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        return $this->finalizer->finalize($request, $next($request));
    }
}
