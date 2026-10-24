<?php

namespace App\Console\Commands;

use App\Support\Architecture\ErpArchitectureMapService;
use Illuminate\Console\Command;

final class ErpArchitectureMapCommand extends Command
{
    protected $signature = 'app:erp-architecture-map';

    protected $description = 'Generate validated ERP sidebar and module architecture maps';

    public function handle(ErpArchitectureMapService $service): int
    {
        $result = $service->generate();
        $this->info("Generated {$result['items']} menu items and {$result['modules']} modules ({$result['edges']} dependency edges).");

        return self::SUCCESS;
    }
}
