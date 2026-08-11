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
        if (! app()->environment('testing') || preg_match('/^schema_admin_testing(?:_|$)/', $database) !== 1) {
            throw new RuntimeException('Operations baseline is restricted to APP_ENV=testing and schema_admin_testing* databases.');
        }

        $iterations = max(1, min(1000, (int) $this->option('iterations')));
        $companyId = DB::table('companies')->orderByDesc('id')->value('id');
        $employeeId = is_numeric($companyId) ? DB::table('employees')->where('company_id', (int) $companyId)->orderBy('id')->value('id') : null;
        $pointStart = hrtime(true);
        for ($index = 0; $index < $iterations; $index++) {
            if (is_numeric($employeeId)) {
                DB::table('employees')->where('id', (int) $employeeId)->first(['id', 'company_id', 'employee_code', 'employment_status']);
            }
        }
        $pointMs = (hrtime(true) - $pointStart) / 1_000_000;
        $filteredStart = hrtime(true);
        for ($index = 0; $index < $iterations; $index++) {
            if (is_numeric($companyId)) {
                DB::table('employees')->where('company_id', (int) $companyId)->where('employment_status', 'active')->orderBy('id')->limit(25)->get(['id', 'employee_code', 'full_name', 'employment_status']);
            }
        }
        $filteredMs = (hrtime(true) - $filteredStart) / 1_000_000;
        $aggregateStart = hrtime(true);
        for ($index = 0; $index < $iterations; $index++) {
            if (is_numeric($companyId)) {
                DB::table('employees')->where('company_id', (int) $companyId)->selectRaw('employment_status, COUNT(*) AS total')->groupBy('employment_status')->get();
            }
        }
        $aggregateMs = (hrtime(true) - $aggregateStart) / 1_000_000;

        Queue::fake();
        $queueStart = hrtime(true);
        for ($index = 0; $index < $iterations; $index++) {
            OperationsProbeJob::dispatch((string) Str::uuid());
        }
        $queueMs = (hrtime(true) - $queueStart) / 1_000_000;

        $this->line(json_encode([
            'iterations' => $iterations,
            'database_point_lookup_ms' => round($pointMs, 2),
            'database_filtered_list_ms' => round($filteredMs, 2),
            'database_aggregate_ms' => round($aggregateMs, 2),
            'queue_dispatch_ms' => round($queueMs, 2),
            'queue_dispatch_per_second' => $queueMs > 0 ? round(($iterations / $queueMs) * 1000, 2) : 0.0,
            'memory_peak_mib' => round(memory_get_peak_usage(true) / 1_048_576, 2),
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
