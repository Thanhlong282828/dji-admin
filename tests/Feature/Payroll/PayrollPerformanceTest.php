<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Payroll\Domain\Services\PayrollRunService;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class PayrollPerformanceTest extends TestCase
{
    use RefreshDatabase;

    #[Group('payroll-performance')]
    public function test_real_calculation_for_one_thousand_employees_preserves_aggregates(): void
    {
        DB::disableQueryLog();
        [$company, $actor, $period, $snapshot] = $this->base('CALC');
        $setupStarted = hrtime(true);
        $employeeIds = $this->employees($company, 1000, 'CALC');
        $components = $this->components($company, $actor);
        $now = now();
        foreach (array_chunk($employeeIds, 200) as $chunk) {
            DB::table('payroll_employee_inputs')->insert(array_map(static fn (int $employee): array => ['company_id' => $company, 'payroll_input_snapshot_id' => $snapshot, 'employee_id' => $employee, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 600, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => $now, 'updated_at' => $now], $chunk));
            $assignments = [];
            foreach ($chunk as $employee) {
                foreach ($components as $component) {
                    $assignments[] = ['company_id' => $company, 'employee_id' => $employee, 'salary_component_id' => $component['id'], 'amount_minor' => $component['amount'], 'currency_code' => 'VND', 'quantity_basis_points' => $component['quantity'], 'rate_minor' => $component['rate'], 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $actor, 'created_at' => $now, 'updated_at' => $now];
                }
            }
            DB::table('employee_salary_component_assignments')->insert($assignments);
        }
        $fixtureMs = (hrtime(true) - $setupStarted) / 1_000_000;
        $service = app(PayrollRunService::class);
        $run = $service->create($company, $period, $snapshot, 'RUN-PERF', 'VND', (string) Str::uuid(), $actor);
        $memoryBefore = memory_get_usage(true);
        $started = hrtime(true);
        $run = $service->calculate($company, $run->id, 1, (string) Str::uuid(), $actor);
        $calculationMs = (hrtime(true) - $started) / 1_000_000;
        $memoryDelta = max(0, memory_get_peak_usage(true) - $memoryBefore);

        self::assertSame(1000, DB::table('payroll_items')->where('payroll_run_id', $run->id)->count());
        self::assertSame(5000, DB::table('payroll_item_components')->where('payroll_run_id', $run->id)->count());
        self::assertSame(1000, DB::table('payroll_items')->where('payroll_run_id', $run->id)->distinct()->count('employee_id'));
        self::assertSame(5700000, (int) DB::table('payroll_items')->where('payroll_run_id', $run->id)->sum('gross_amount_minor'));
        self::assertSame(5600000, (int) DB::table('payroll_items')->where('payroll_run_id', $run->id)->sum('net_amount_minor'));
        self::assertSame($run->gross_total_minor, (int) DB::table('payroll_items')->where('payroll_run_id', $run->id)->sum('gross_amount_minor'));
        self::assertSame(1, $run->calculation_version);
        fwrite(STDERR, sprintf("\nPAYROLL_CALC_BENCH fixture_ms=%.2f calculation_ms=%.2f peak_memory_delta=%d employees=1000 items=1000 breakdowns=5000 chunk=200\n", $fixtureMs, $calculationMs, $memoryDelta));
    }

    #[Group('payroll-large-export')]
    public function test_five_thousand_item_export_consumes_cursor_with_one_bom_and_stable_final_row(): void
    {
        DB::disableQueryLog();
        [$company, $actor, $period, $snapshot] = $this->base('EXPORT');
        Permission::findOrCreate('payroll.export', 'web');
        $user = User::query()->findOrFail($actor);
        $user->givePermissionTo('payroll.export');
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor]);
        $setupStarted = hrtime(true);
        $employeeIds = $this->employees($company, 5000, 'EXPORT');
        $service = app(PayrollRunService::class);
        $run = $service->create($company, $period, $snapshot, 'RUN-EXPORT', 'VND', (string) Str::uuid(), $actor);
        $now = now();
        $sequence = 0;
        foreach (array_chunk($employeeIds, 250) as $chunk) {
            $rows = [];
            foreach ($chunk as $employee) {
                $number = ++$sequence;
                $rows[] = ['uuid' => (string) Str::uuid(), 'company_id' => $company, 'payroll_run_id' => $run->id, 'employee_id' => $employee, 'gross_salary' => 5700, 'insurance' => 0, 'personal_income_tax' => 0, 'deductions' => 100, 'net_salary' => 5600, 'breakdown' => '[]', 'calculation_version' => 1, 'employee_number_snapshot' => sprintf('EXPORT-%04d', $number), 'employee_name_snapshot' => 'Export Employee '.$number, 'currency_code' => 'VND', 'gross_amount_minor' => 5700, 'deduction_amount_minor' => 100, 'employer_contribution_amount_minor' => 300, 'net_amount_minor' => 5600, 'calculation_status' => 'calculated', 'validation_status' => 'valid', 'calculated_at' => $now, 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('payroll_items')->insert($rows);
        }
        $fixtureMs = (hrtime(true) - $setupStarted) / 1_000_000;
        $memoryBefore = memory_get_usage(true);
        $started = hrtime(true);
        $response = $this->actingAs($user)->get(route('admin.payroll.runs.export', ['run' => $run->id, 'company_id' => $company]))->assertOk();
        $streamStartMs = (hrtime(true) - $started) / 1_000_000;
        $content = $response->streamedContent();
        $streamMs = (hrtime(true) - $started) / 1_000_000;
        $memoryDelta = max(0, memory_get_peak_usage(true) - $memoryBefore);

        self::assertSame(1, substr_count($content, "\xEF\xBB\xBF"));
        $lines = preg_split('/\r\n|\n|\r/', trim(substr($content, 3)));
        self::assertIsArray($lines);
        self::assertCount(5001, $lines);
        self::assertStringContainsString('"Run Number","Period Start"', $lines[0]);
        self::assertStringContainsString('EXPORT-5000', $lines[5000]);
        self::assertSame(5000, count(array_unique(array_slice($lines, 1))));
        fwrite(STDERR, sprintf("\nPAYROLL_EXPORT_BENCH fixture_ms=%.2f stream_start_ms=%.2f full_stream_ms=%.2f peak_memory_delta=%d rows=5000 chunk=250 cursor=yes\n", $fixtureMs, $streamStartMs, $streamMs, $memoryDelta));
    }

    /** @return array{int,int,int,int} */
    private function base(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PF-'.$code, 'name' => 'Schema Payroll Performance', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $actor = User::factory()->create();
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => '2026-07', 'name' => 'July', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $company->id, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $actor->id, 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return [$company->id, $actor->id, $period, $snapshot];
    }

    /** @return list<int> */
    private function employees(int $company, int $count, string $prefix): array
    {
        $now = now();
        foreach (array_chunk(range(1, $count), 250) as $numbers) {
            DB::table('employees')->insert(array_map(static fn (int $number): array => ['uuid' => (string) Str::uuid(), 'company_id' => $company, 'employee_code' => sprintf('%s-%04d', $prefix, $number), 'full_name' => 'Payroll Employee '.$number, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time', 'created_at' => $now, 'updated_at' => $now], $numbers));
        }

        $ids = [];
        foreach (DB::table('employees')->where('company_id', $company)->orderBy('id')->pluck('id') as $id) {
            $ids[] = is_numeric($id) ? (int) $id : 0;
        }

        return $ids;
    }

    /** @return list<array{id:int,amount:int|null,quantity:int|null,rate:int|null}> */
    private function components(int $company, int $actor): array
    {
        $definitions = [['BASIC', 'Basic', 'earning', 'fixed', 5000, null, null], ['ALLOW', 'Allowance', 'earning', 'fixed', 500, null, null], ['OT', 'Overtime', 'earning', 'quantity_rate', null, 100000, 20], ['UNPAID', 'Unpaid', 'deduction', 'fixed', 100, null, null], ['EMPLOYER', 'Employer', 'employer_contribution', 'fixed', 300, null, null]];
        $result = [];
        foreach ($definitions as $order => $row) {
            $id = (int) DB::table('salary_components')->insertGetId(['company_id' => $company, 'code' => $row[0], 'name' => $row[1], 'category' => $row[2], 'calculation_type' => $row[3], 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => $row[2] === 'earning', 'affects_net' => $row[2] !== 'employer_contribution', 'display_order' => $order, 'status' => 'active', 'version' => 1, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            $result[] = ['id' => $id, 'amount' => $row[4], 'quantity' => $row[5], 'rate' => $row[6]];
        }

        return $result;
    }
}
