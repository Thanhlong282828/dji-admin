<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\DataLifecycle\DataLifecycleAuditService;
use Illuminate\Contracts\View\View;

final class DataLifecycleController extends Controller
{
    public function __invoke(DataLifecycleAuditService $service): View
    {
        return view('operations.data-lifecycle', ['snapshot' => $service->snapshot()]);
    }
}
