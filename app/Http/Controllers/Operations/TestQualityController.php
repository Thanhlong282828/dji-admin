<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\TestGovernance\TestQualitySnapshotService;
use Illuminate\Contracts\View\View;

final class TestQualityController extends Controller
{
    public function __invoke(TestQualitySnapshotService $service): View
    {
        return view('operations.test-quality', ['snapshot' => $service->snapshot()]);
    }
}
