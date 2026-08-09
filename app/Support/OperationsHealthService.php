<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Stringable;
use Throwable;

final class OperationsHealthService
{
    /** @return array{status: 'ready'|'degraded', checks: array{database: bool, redis: bool, queue: bool}, timestamp: string} */
    public function readiness(): array
    {
        $database = $this->databaseIsReady();
        $redis = $this->redisIsReady();
        $queue = in_array(config('queue.default'), ['redis', 'database', 'sqs'], true);

        return [
            'status' => $database && $redis && $queue ? 'ready' : 'degraded',
            'checks' => ['database' => $database, 'redis' => $redis, 'queue' => $queue],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function databaseIsReady(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function redisIsReady(): bool
    {
        try {
            $result = Redis::connection()->ping();

            return $result === true
                || $result === 'PONG'
                || ($result instanceof Stringable && (string) $result === 'PONG');
        } catch (Throwable) {
            return false;
        }
    }
}
