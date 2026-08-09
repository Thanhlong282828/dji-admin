<?php

namespace App\Console\Commands;

use App\Support\OperationsHealthService;
use Illuminate\Console\Command;

final class OperationsHealthCheckCommand extends Command
{
    protected $signature = 'operations:health-check';

    protected $description = 'Check database, Redis, and queue readiness without exposing configuration';

    public function handle(OperationsHealthService $health): int
    {
        $result = $health->readiness();
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['status'] === 'ready' ? self::SUCCESS : self::FAILURE;
    }
}
