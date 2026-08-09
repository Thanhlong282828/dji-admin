<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\ReliabilitySnapshotService;
use Illuminate\Contracts\View\View;

final class ReliabilityController extends Controller
{
    public function __invoke(ReliabilitySnapshotService $service): View
    {
        return view('operations.reliability', ['snapshot' => $service->snapshot()]);
    }
}
