<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\ObservabilitySnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

final class ObservabilityController extends Controller
{
    public function index(ObservabilitySnapshotService $service): View
    {
        return view('operations.observability', ['snapshot' => $service->snapshot()]);
    }

    public function metrics(ObservabilitySnapshotService $service): JsonResponse
    {
        $snapshot = $service->snapshot();

        return response()->json([
            'http' => $snapshot['http'],
            'queue' => $snapshot['reliability']['queue'],
            'outboxes' => $snapshot['reliability']['outboxes'],
            'integration' => $snapshot['reliability']['integration'],
            'scheduler' => $snapshot['reliability']['scheduler'],
            'dependencies' => $snapshot['reliability']['readiness']['checks'],
            'slo' => $snapshot['reliability']['slo'],
            'burn_rate' => $snapshot['burn_rate'],
            'ddos' => $snapshot['ddos'],
            'document_storage' => $snapshot['document_storage'],
            'production_verification' => $snapshot['production_verification'],
            'alerts' => $snapshot['alerts'],
            'generated_at' => $snapshot['generated_at'],
        ]);
    }
}
