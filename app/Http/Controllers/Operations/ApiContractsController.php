<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\ApiContract\ApiContractAuditService;
use Illuminate\Contracts\View\View;

final class ApiContractsController extends Controller
{
    public function __invoke(ApiContractAuditService $service): View
    {
        return view('operations.api-contracts', ['snapshot' => $service->snapshot()]);
    }
}
