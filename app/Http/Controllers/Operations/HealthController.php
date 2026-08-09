<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\OperationsHealthService;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'alive', 'timestamp' => now()->toIso8601String()]);
    }

    public function ready(OperationsHealthService $health): JsonResponse
    {
        $result = $health->readiness();

        return response()->json($result, $result['status'] === 'ready' ? 200 : 503);
    }

    public function metricsReadiness(OperationsHealthService $health): JsonResponse
    {
        $result = $health->readiness();

        return response()->json([
            'metrics_ready' => $result['status'] === 'ready',
            'components' => array_keys(array_filter($result['checks'])),
            'timestamp' => $result['timestamp'],
        ], $result['status'] === 'ready' ? 200 : 503);
    }
}
