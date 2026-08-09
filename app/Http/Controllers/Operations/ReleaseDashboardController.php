<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\Release\ReleaseDashboardService;
use Illuminate\Contracts\View\View;

final class ReleaseDashboardController extends Controller
{
    public function __invoke(ReleaseDashboardService $service): View
    {
        return view('operations.releases', $service->snapshot());
    }
}
