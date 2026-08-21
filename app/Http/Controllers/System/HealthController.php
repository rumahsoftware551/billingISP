<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]);
    }

    public function ready(Request $request, SystemHealthService $health): JsonResponse
    {
        $expected = (string) config('jaringanku.health_token', '');
        if ($expected !== '') {
            $provided = (string) ($request->bearerToken() ?: $request->header('X-Health-Token', ''));
            abort_unless(hash_equals($expected, $provided), 404);
        }

        $result = $health->readiness();
        return response()->json($result, $result['ready'] ? 200 : 503);
    }
}
