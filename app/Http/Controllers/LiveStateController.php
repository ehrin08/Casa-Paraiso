<?php

namespace App\Http\Controllers;

use App\Services\LiveStateFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveStateController extends Controller
{
    public function __invoke(Request $request, LiveStateFingerprint $fingerprint): JsonResponse
    {
        return response()->json(['scopes' => $fingerprint->forUser($request->user())])
            ->header('Cache-Control', 'private, no-store');
    }
}
