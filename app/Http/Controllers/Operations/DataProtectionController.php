<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\DataProtection\CryptographicInventoryService;
use App\Support\DataProtection\DataFieldRegistry;
use App\Support\DataProtection\DataProtectionAuditService;
use Illuminate\Contracts\View\View;

final class DataProtectionController extends Controller
{
    public function __invoke(DataProtectionAuditService $audit, DataFieldRegistry $registry, CryptographicInventoryService $crypto): View
    {
        return view('operations.data-protection', ['audit' => $audit->audit(), 'fields' => $registry->entries(), 'crypto' => $crypto->entries()]);
    }
}
