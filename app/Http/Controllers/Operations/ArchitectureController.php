<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\Architecture\ArchitectureAuditService;
use Illuminate\View\View;

final class ArchitectureController extends Controller
{
    public function __invoke(ArchitectureAuditService $service): View
    {
        return view('operations.architecture', ['snapshot' => $service->snapshot()]);
    }
}
