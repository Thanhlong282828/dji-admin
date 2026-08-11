<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Infrastructure\Persistence\Repositories\EloquentEmployeeReadRepository;
use Tests\TestCase;

final class EmployeeExportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_query_count_is_bounded_when_employee_count_grows(): void
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PERF', 'name' => 'Performance Testing', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $this->employees($company->id, 10, 0);
        $small = $this->measure($company->id);
        $this->employees($company->id, 50, 10);
        $large = $this->measure($company->id);
        self::assertSame(10, $small['rows']);
        self::assertSame(60, $large['rows']);
        self::assertLessThanOrEqual($small['queries'] + 2, $large['queries']);
        self::assertLessThanOrEqual(8, $large['queries']);
    }

    /** @return array{queries:int,rows:int} */
    private function measure(int $companyId): array
    {
        $queries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries++;
            }
        });
        $rows = iterator_count(app(EloquentEmployeeReadRepository::class)->exportRows($companyId, []));

        return ['queries' => $queries, 'rows' => $rows];
    }

    private function employees(int $companyId, int $count, int $offset): void
    {
        for ($index = 1; $index <= $count; $index++) {
            Employee::query()->create(['company_id' => $companyId, 'employee_code' => sprintf('PERF-%05d', $offset + $index), 'full_name' => 'Synthetic Employee '.($offset + $index), 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        }
    }
}
