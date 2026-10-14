<?php

namespace App\Console\Commands;

use App\Support\CloudflareDdosMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class OperationsHeartbeatCommand extends Command
{
    protected $signature = 'operations:heartbeat';

    protected $description = 'Publish the scheduler heartbeat without mutating business state';

    public function handle(CloudflareDdosMonitor $ddosMonitor): int
    {
        Cache::put('operations:scheduler:heartbeat', now()->toIso8601String(), now()->addMinutes(10));
        $ddosMonitor->snapshot(true);

        return self::SUCCESS;
    }
}
