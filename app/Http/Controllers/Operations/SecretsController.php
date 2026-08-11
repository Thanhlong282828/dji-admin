<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\Secrets\SecretGovernanceService;
use Illuminate\Contracts\View\View;

final class SecretsController extends Controller
{
    public function __invoke(SecretGovernanceService $service): View
    {
        return view('operations.secrets', ['entries' => $service->entries(), 'readiness' => $service->readiness()]);
    }
}
