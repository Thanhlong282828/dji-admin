<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\Continuity\ContinuityDashboardService;
use Illuminate\Contracts\View\View;

final class ContinuityController extends Controller
{
    public function __invoke(ContinuityDashboardService $service): View
    {
        return view('operations.continuity', $service->snapshot());
    }
}
