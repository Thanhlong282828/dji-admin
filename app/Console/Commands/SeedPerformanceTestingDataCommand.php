<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Employee\Infrastructure\Persistence\Repositories\EloquentEmployeeReadRepository;
use RuntimeException;

final class SeedPerformanceTestingDataCommand extends Command
{
    protected $signature = 'performance:seed-testing-data {--rows=1000 : Synthetic employee rows} {--cleanup : Remove only the deterministic performance dataset} {--measure : Measure the bounded Employee export after seeding}';

    protected $description = 'Create or clean a deterministic testing-only performance dataset';

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();
        if (app()->environment() !== 'testing' || preg_match('/^schema_admin_testing(?:_|$)/', $database) !== 1) {
            throw new RuntimeException('Performance data is restricted to APP_ENV=testing and schema_admin_testing* databases.');
        }
        if ($this->option('cleanup')) {
            $companyId = DB::table('companies')->where('code', 'PERF-DATASET')->value('id');
            if (is_numeric($companyId)) {
                DB::table('employees')->where('company_id', (int) $companyId)->delete();
                DB::table('companies')->where('id', (int) $companyId)->delete();
            }
            $this->info('Performance dataset cleaned.');

            return self::SUCCESS;
        }

        $rows = max(1, min(10_000, (int) $this->option('rows')));
        DB::table('companies')->updateOrInsert(['code' => 'PERF-DATASET'], ['uuid' => '00000000-0000-4000-8000-000000000018', 'name' => 'Synthetic Performance Dataset', 'country_code' => 'VN', 'currency_code' => 'VND', 'currency' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'status' => 'active', 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
        $companyId = DB::table('companies')->where('code', 'PERF-DATASET')->value('id');
        if (! is_numeric($companyId)) {
            throw new RuntimeException('Unable to resolve the synthetic company.');
        }
        foreach (range(1, $rows) as $batchStart) {
            if (($batchStart - 1) % 500 !== 0) {
                continue;
            }
            $batch = [];
            for ($index = $batchStart; $index <= min($rows, $batchStart + 499); $index++) {
                $batch[] = ['uuid' => (string) Str::uuid(), 'company_id' => (int) $companyId, 'employee_code' => sprintf('PERF-%05d', $index), 'full_name' => sprintf('Synthetic Employee %05d', $index), 'work_email' => sprintf('employee-%05d@performance.test', $index), 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => $index % 10 === 0 ? 'probation' : 'active', 'employment_type' => 'full_time', 'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('employees')->insertOrIgnore($batch);
        }
        $count = DB::table('employees')->where('company_id', (int) $companyId)->count();
        $this->info("Performance dataset ready: {$count} employees in {$database}.");
        if ($this->option('measure')) {
            $this->measure((int) $companyId);
        }

        return self::SUCCESS;
    }

    private function measure(int $companyId): void
    {
        $queries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries++;
            }
        });
        $start = hrtime(true);
        $memory = memory_get_usage(true);
        $rows = 0;
        $checksum = hash_init('sha256');
        foreach (app(EloquentEmployeeReadRepository::class)->exportRows($companyId, []) as $row) {
            $rows++;
            hash_update($checksum, json_encode($row, JSON_THROW_ON_ERROR));
        }
        $this->line(json_encode(['rows' => $rows, 'queries' => $queries, 'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2), 'memory_delta_bytes' => memory_get_usage(true) - $memory, 'peak_bytes' => memory_get_peak_usage(true), 'checksum' => hash_final($checksum)], JSON_THROW_ON_ERROR));
    }
}
