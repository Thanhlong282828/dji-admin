<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\Security\SecurityAssuranceService;
use Illuminate\Contracts\View\View;

final class SecurityAssuranceController extends Controller
{
    public function __invoke(SecurityAssuranceService $service): View
    {
        return view('operations.security-assurance', $service->snapshot());
    }
}
