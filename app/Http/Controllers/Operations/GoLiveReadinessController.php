<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\GoLive\ProductionReadinessService;
use Illuminate\Contracts\View\View;

final class GoLiveReadinessController extends Controller
{
    public function __invoke(ProductionReadinessService $service): View
    {
        return view('operations.go-live', ['snapshot' => $service->snapshot()]);
    }
}
