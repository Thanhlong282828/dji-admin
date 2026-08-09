<?php

namespace App\Console\Commands;

use App\Jobs\OperationsProbeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;

final class OperationsBaselineCommand extends Command
{
    protected $signature = 'operations:baseline {--iterations=100}';

    protected $description = 'Measure testing-only database and queue dispatch baselines';

    public function handle(): int
    {
        $connection = config('database.default');
        if (! is_string($connection)) {
            throw new RuntimeException('The default database connection is invalid.');
        }
        $database = config("database.connections.{$connection}.database");
        if (! is_string($database)) {
            throw new RuntimeException('The database name is invalid.');
        }
        if (! app()->environment('testing') || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException('Operations baseline is restricted to APP_ENV=testing and a *_testing database.');
        }

        $iterations = max(1, min(1000, (int) $this->option('iterations')));
        $dbStart = hrtime(true);
        for ($index = 0; $index < $iterations; $index++) {
            DB::select('SELECT 1');
        }
        $dbMs = (hrtime(true) - $dbStart) / 1_000_000;

        Queue::fake();
        $queueStart = hrtime(true);
        for ($index = 0; $index < $iterations; $index++) {
            OperationsProbeJob::dispatch((string) Str::uuid());
        }
        $queueMs = (hrtime(true) - $queueStart) / 1_000_000;

        $this->line(json_encode([
            'iterations' => $iterations,
            'database_ms' => round($dbMs, 2),
            'queue_dispatch_ms' => round($queueMs, 2),
            'memory_peak_mib' => round(memory_get_peak_usage(true) / 1_048_576, 2),
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
