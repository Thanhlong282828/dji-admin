<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\Performance\PerformanceSnapshotService;
use Illuminate\View\View;

final class PerformanceController extends Controller
{
    public function __invoke(PerformanceSnapshotService $service): View
    {
        return view('operations.performance', ['snapshot' => $service->snapshot()]);
    }
}
