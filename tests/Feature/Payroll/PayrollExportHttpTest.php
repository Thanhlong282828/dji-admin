<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Services\PayrollRunService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class PayrollExportHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('payroll.export', 'web');
    }

    public function test_employee_and_component_exports_have_stable_safe_csv_contracts(): void
    {
        $fixture = $this->fixture('=EMP-001', '@Nguyễn Schema');
        $user = $fixture['user'];
        $user->givePermissionTo('payroll.export');

        $employeeResponse = $this->actingAs($user)->get($this->url($fixture, 'admin.payroll.runs.export'));
        $employeeResponse->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        self::assertMatchesRegularExpression('/attachment; filename=payroll-run-\d+-\d{8}-\d{6}\.csv/', (string) $employeeResponse->headers->get('content-disposition'));
        $employeeCsv = $employeeResponse->streamedContent();
        self::assertSame("\xEF\xBB\xBF", substr($employeeCsv, 0, 3));
        $employeeRows = $this->rows($employeeCsv);
        self::assertSame(['Run Number', 'Period Start', 'Period End', 'Input Snapshot Version', 'Employee Number', 'Employee Name', 'Organization Unit', 'Job Position', 'Currency', 'Scheduled Minutes', 'Worked Minutes', 'Paid Leave Minutes', 'Unpaid Leave Minutes', 'Approved Overtime Minutes', 'Gross Amount', 'Employee Deduction Amount', 'Employer Contribution Amount', 'Net Amount', 'Calculation Status', 'Validation Status', 'Calculation Version', 'Calculated At'], $employeeRows[0]);
        self::assertCount(2, $employeeRows);
        self::assertSame("'=EMP-001", $employeeRows[1][4]);
        self::assertSame("'@Nguyễn Schema", $employeeRows[1][5]);
        self::assertSame('5700', $employeeRows[1][14]);
        self::assertSame('5600', $employeeRows[1][17]);
        self::assertSame('1', $employeeRows[1][20]);

        $componentResponse = $this->actingAs($user)->get($this->url($fixture, 'admin.payroll.runs.components-export'));
        $componentResponse->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $componentCsv = $componentResponse->streamedContent();
        self::assertSame("\xEF\xBB\xBF", substr($componentCsv, 0, 3));
        $componentRows = $this->rows($componentCsv);
        self::assertSame(['Run Number', 'Employee Number', 'Employee Name', 'Component Code', 'Component Name', 'Category', 'Calculation Type', 'Source Type', 'Quantity Basis Points', 'Rate Minor', 'Percentage Basis Points', 'Base Amount Minor', 'Amount Minor', 'Currency', 'Calculation Order', 'Calculation Version'], $componentRows[0]);
        self::assertCount(6, $componentRows);
        self::assertSame(["'=EMP-001", "'@Nguyễn Schema"], array_slice($componentRows[1], 1, 2));
        self::assertSame(['BASIC', 'ALLOW', 'OT', 'UNPAID', 'EMPLOYER'], array_column(array_slice($componentRows, 1), 3));
        self::assertSame(['1', '2', '3', '4', '5'], array_column(array_slice($componentRows, 1), 14));

        $lower = strtolower($employeeCsv.$componentCsv);
        foreach (['bank_account', 'tax_identifier', 'insurance_identifier', 'idempotency_key', 'raw metadata', 'password', 'token'] as $sensitive) {
            self::assertStringNotContainsString($sensitive, $lower);
        }
    }

    public function test_export_authentication_permission_company_access_and_foreign_run_are_enforced(): void
    {
        $a = $this->fixture('A-001', 'Employee A');
        $b = $this->fixture('B-001', 'Employee B');
        $this->get($this->url($a, 'admin.payroll.runs.export'))->assertRedirect();
        $this->actingAs($a['user'])->get($this->url($a, 'admin.payroll.runs.export'))->assertForbidden();
        $a['user']->givePermissionTo('payroll.export');
        $this->actingAs($a['user'])->get($this->url($a, 'admin.payroll.runs.export'))->assertOk();
        $this->actingAs($a['user'])->get(route('admin.payroll.runs.export', ['run' => $b['run'], 'company_id' => $a['company']]))->assertNotFound();
        $this->actingAs($a['user'])->get(route('admin.payroll.runs.export', ['run' => $b['run'], 'company_id' => $b['company']]))->assertForbidden();
    }

    public function test_filters_are_combined_and_cannot_escape_tenant(): void
    {
        $a = $this->fixture('FILTER-A', 'Employee Filter A');
        $b = $this->fixture('FILTER-B', 'Employee Filter B');
        $a['user']->givePermissionTo('payroll.export');
        $content = $this->actingAs($a['user'])->get($this->url($a, 'admin.payroll.runs.export', ['employee_id' => $a['employee'], 'calculation_status' => 'calculated', 'validation_status' => 'valid', 'currency_code' => 'vnd']))->assertOk()->streamedContent();
        self::assertStringContainsString('FILTER-A', $content);
        self::assertStringNotContainsString('FILTER-B', $content);
        $foreign = $this->actingAs($a['user'])->get($this->url($a, 'admin.payroll.runs.export', ['employee_id' => $b['employee']]))->assertOk()->streamedContent();
        self::assertCount(1, $this->rows($foreign));
    }

    /** @return array{company:int,employee:int,user:User,run:int} */
    private function fixture(string $employeeCode, string $employeeName): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PE-'.Str::upper(Str::random(6)), 'name' => 'Schema Payroll Export', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => $employeeCode, 'full_name' => $employeeName, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => '2026-07', 'name' => 'July', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $company->id, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $user->id, 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payroll_employee_inputs')->insert(['company_id' => $company->id, 'payroll_input_snapshot_id' => $snapshot, 'employee_id' => $employee->id, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 600, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['BASIC', 'Basic', 'earning', 'fixed', 5000, null, null], ['ALLOW', 'Allowance', 'earning', 'fixed', 500, null, null], ['OT', 'Overtime', 'earning', 'quantity_rate', null, 100000, 20], ['UNPAID', 'Unpaid', 'deduction', 'fixed', 100, null, null], ['EMPLOYER', 'Employer', 'employer_contribution', 'fixed', 300, null, null]] as $order => $row) {
            $component = (int) DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => $row[0], 'name' => $row[1], 'category' => $row[2], 'calculation_type' => $row[3], 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => $row[2] === 'earning', 'affects_net' => $row[2] !== 'employer_contribution', 'display_order' => $order, 'status' => 'active', 'version' => 1, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('employee_salary_component_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $component, 'amount_minor' => $row[4], 'currency_code' => 'VND', 'quantity_basis_points' => $row[5], 'rate_minor' => $row[6], 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $service = app(PayrollRunService::class);
        $run = $service->create($company->id, $period, $snapshot, 'RUN-'.Str::upper(Str::random(6)), 'VND', (string) Str::uuid(), $user->id);
        $run = $service->calculate($company->id, $run->id, 1, (string) Str::uuid(), $user->id);

        return ['company' => $company->id, 'employee' => $employee->id, 'user' => $user, 'run' => $run->id];
    }

    /**
     * @param  array{company:int,run:int}  $fixture
     * @param  array<string, int|string>  $filters
     */
    private function url(array $fixture, string $route, array $filters = []): string
    {
        return route($route, ['run' => $fixture['run'], 'company_id' => $fixture['company']] + $filters);
    }

    /** @return list<list<string>> */
    private function rows(string $content): array
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = array_map(static fn (?string $cell): string => $cell ?? '', $row);
        }
        fclose($stream);

        return $rows;
    }
}
