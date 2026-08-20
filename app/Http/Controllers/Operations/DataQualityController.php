<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\DataQuality\DataQualityAuditService;
use Illuminate\View\View;

final class DataQualityController extends Controller
{
    public function __invoke(DataQualityAuditService $service): View
    {
        return view('operations.data-quality', ['snapshot' => $service->snapshot()]);
    }
}
