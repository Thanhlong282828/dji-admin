<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use Illuminate\Contracts\View\View;

final class RuntimeConfigurationController extends Controller
{
    public function __invoke(RuntimeConfigurationService $service): View
    {
        $schemaVersion = config('runtime-configuration.schema_version', 1);

        return view('operations.configuration', ['entries' => $service->entries(), 'checksum' => $service->snapshotChecksum(), 'schemaVersion' => is_int($schemaVersion) ? $schemaVersion : 1]);
    }
}
