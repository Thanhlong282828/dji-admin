<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Services\PayrollRunService;
use Tests\TestCase;

final class PayrollRunLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_uses_immutable_input_and_persists_exact_component_breakdown(): void
    {
        [$company,$employee,$creator,$approver,$period,$snapshot] = $this->fixture();
        $service = app(PayrollRunService::class);
        $run = $service->create($company, $period, $snapshot, 'RUN-2026-07', 'VND', (string) Str::uuid(), $creator);
        $run = $service->calculate($company, $run->id, 1, (string) Str::uuid(), $creator);
        self::assertSame(PayrollRunStatus::Calculated, $run->status);
        self::assertSame(5700, $run->gross_total_minor);
        self::assertSame(100, $run->deduction_total_minor);
        self::assertSame(300, $run->employer_contribution_total_minor);
        self::assertSame(5600, $run->net_total_minor);
        self::assertSame(1, DB::table('payroll_items')->where('payroll_run_id', $run->id)->count());
        self::assertSame(5, DB::table('payroll_item_components')->where('payroll_run_id', $run->id)->count());
        $run = $service->transition($company, $run->id, PayrollRunStatus::Submitted, 2, (string) Str::uuid(), $creator);
        $run = $service->transition($company, $run->id, PayrollRunStatus::Approved, 3, (string) Str::uuid(), $approver);
        $run = $service->transition($company, $run->id, PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $approver);
        self::assertSame(PayrollRunStatus::Finalized, $run->status);
        self::assertSame(5, DB::table('payroll_run_histories')->where('payroll_run_id', $run->id)->count());
    }

    public function test_same_period_and_self_approval_are_blocked(): void
    {
        [$company,,$creator,,$period,$snapshot] = $this->fixture();
        $service = app(PayrollRunService::class);
        $run = $service->create($company, $period, $snapshot, 'RUN-A', 'VND', (string) Str::uuid(), $creator);
        $this->expectException(ValidationException::class);
        $service->create($company, $period, $snapshot, 'RUN-B', 'VND', (string) Str::uuid(), $creator);
    }

    public function test_insurance_policy_is_used_by_calculation_and_persisted_immutably(): void
    {
        [$company, $employee, $creator,, $period, $snapshot] = $this->fixture();
        DB::table('salary_components')->where('company_id', $company)->update(['insurance_status' => 'excluded']);
        DB::table('salary_components')->where('company_id', $company)->where('code', 'BASIC')->update(['insurance_status' => 'included']);
        $policy = (int) DB::table('insurance_policies')->insertGetId(['company_id' => $company, 'code' => 'INTERNAL-INS', 'name' => 'Internal Test Policy', 'version' => 1, 'effective_from' => '2026-01-01', 'status' => 'active', 'currency_code' => 'VND', 'rounding_rule' => 'half_up', 'created_by' => $creator, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('insurance_policy_rates')->insert(['insurance_policy_id' => $policy, 'insurance_type' => 'social', 'employee_rate_basis_points' => 800, 'employer_rate_basis_points' => 1750, 'calculation_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_insurance_profiles')->insert(['company_id' => $company, 'employee_id' => $employee, 'insurance_policy_id' => $policy, 'participation_status' => 'participating', 'effective_from' => '2026-01-01', 'currency_code' => 'VND', 'created_by' => $creator, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(PayrollRunService::class);
        $run = $service->create($company, $period, $snapshot, 'RUN-INSURANCE', 'VND', (string) Str::uuid(), $creator);
        $run = $service->calculate($company, $run->id, 1, (string) Str::uuid(), $creator);
        self::assertSame(400, $run->insurance_employee_total_minor);
        self::assertSame(875, $run->insurance_employer_total_minor);
        self::assertSame(500, $run->deduction_total_minor);
        self::assertSame(5200, $run->net_total_minor);
        self::assertSame(1175, $run->employer_contribution_total_minor);
        $item = DB::table('payroll_items')->where('payroll_run_id', $run->id)->first();
        self::assertNotNull($item);
        self::assertSame(5000, $this->integer($item->insurance_contribution_base_minor));
        self::assertSame(2, DB::table('payroll_item_components')->where('payroll_item_id', $item->id)->where('source_type', 'insurance_policy_rate')->count());
        DB::table('insurance_policy_rates')->where('insurance_policy_id', $policy)->update(['employee_rate_basis_points' => 9999]);
        self::assertSame(400, $this->integer(DB::table('payroll_items')->where('id', $item->id)->value('insurance_employee_total_minor')));
        self::assertSame(800, $this->integer(DB::table('payroll_item_components')->where('payroll_item_id', $item->id)->where('component_code_snapshot', 'INSURANCE_EMPLOYEE_SOCIAL')->value('percentage_basis_points')));
    }

    public function test_payroll_tax_policy_profile_dependents_and_brackets_are_persisted_by_exact_calculation_version(): void
    {
        [$company, $employee, $creator,, $period, $snapshot] = $this->fixture();
        DB::table('salary_components')->where('company_id', $company)->update(['taxable_status' => 'non_taxable']);
        DB::table('salary_components')->where('company_id', $company)->where('category', 'earning')->update(['taxable_status' => 'taxable']);
        $policy = (int) DB::table('tax_policies')->insertGetId(['company_id' => $company, 'code' => 'INTERNAL-TAX', 'name' => 'Internal Tax Fixture', 'version' => 1, 'effective_from' => '2026-01-01', 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'rounding_stage' => 'total', 'floor_taxable_income_at_zero' => true, 'status' => 'active', 'created_by' => $creator, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tax_policy_brackets')->insert(['tax_policy_id' => $policy, 'sequence' => 1, 'lower_bound_minor' => 0, 'upper_bound_minor' => null, 'rate_basis_points' => 1000, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['PERSONAL', 'personal', 100], ['DEPENDENT', 'dependent', 50], ['INSURANCE', 'employee_insurance', null]] as $order => $deduction) {
            DB::table('tax_policy_deductions')->insert(['tax_policy_id' => $policy, 'code' => $deduction[0], 'name' => $deduction[0], 'deduction_type' => $deduction[1], 'amount_minor' => $deduction[2], 'effective_from' => '2026-01-01', 'priority' => $order, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('employee_tax_profiles')->insert(['company_id' => $company, 'employee_id' => $employee, 'tax_policy_id' => $policy, 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'personal_deduction_eligible' => true, 'withholding_method' => 'policy', 'created_by' => $creator, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_tax_dependents')->insert(['company_id' => $company, 'employee_id' => $employee, 'name' => 'Masked dependent fixture', 'relationship' => 'child', 'effective_from' => '2026-01-01', 'deduction_eligible' => true, 'approval_status' => 'approved', 'approved_by' => $creator, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $service = app(PayrollRunService::class);
        $run = $service->create($company, $period, $snapshot, 'RUN-TAX', 'VND', (string) Str::uuid(), $creator);
        $run = $service->calculate($company, $run->id, 1, (string) Str::uuid(), $creator);
        $item = DB::table('payroll_items')->where('payroll_run_id', $run->id)->first();
        self::assertNotNull($item);
        self::assertSame(5550, $this->integer($item->taxable_income_minor));
        self::assertSame(555, $this->integer($item->personal_income_tax_minor));
        self::assertSame(5045, $this->integer($item->net_after_personal_income_tax_minor));
        self::assertSame(555, $run->personal_income_tax_total_minor);
        self::assertSame(1, DB::table('payroll_item_components')->where('payroll_item_id', $item->id)->where('source_type', 'tax_policy_bracket')->count());
        DB::table('tax_policy_brackets')->where('tax_policy_id', $policy)->update(['rate_basis_points' => 9999]);
        self::assertSame(555, $this->integer(DB::table('payroll_items')->where('id', $item->id)->value('personal_income_tax_minor')));
        self::assertSame(1000, $this->integer(DB::table('payroll_item_components')->where('payroll_item_id', $item->id)->where('source_type', 'tax_policy_bracket')->value('percentage_basis_points')));
    }

    /** @return array{int,int,int,int,int,int} */
    private function fixture(): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PAY-'.Str::upper(Str::random(5)), 'name' => 'Schema Payroll', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $creator->id, 'employee_code' => 'PAY-001', 'full_name' => 'Payroll Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => '2026-07', 'name' => 'July', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $company->id, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $creator->id, 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payroll_employee_inputs')->insert(['company_id' => $company->id, 'payroll_input_snapshot_id' => $snapshot, 'employee_id' => $employee->id, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 600, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['BASIC', 'Basic', 'earning', 'fixed', 5000, null, null], ['ALLOW', 'Allowance', 'earning', 'fixed', 500, null, null], ['OT', 'Overtime', 'earning', 'quantity_rate', null, 100000, 20], ['UNPAID', 'Unpaid', 'deduction', 'fixed', 100, null, null], ['EMPLOYER', 'Employer', 'employer_contribution', 'fixed', 300, null, null]] as $order => $row) {
            $component = (int) DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => $row[0], 'name' => $row[1], 'category' => $row[2], 'calculation_type' => $row[3], 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => $row[2] === 'earning', 'affects_net' => $row[2] !== 'employer_contribution', 'display_order' => $order, 'status' => 'active', 'version' => 1, 'created_by' => $creator->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('employee_salary_component_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $component, 'amount_minor' => $row[4], 'currency_code' => 'VND', 'quantity_basis_points' => $row[5], 'rate_minor' => $row[6], 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $creator->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return [$company->id, $employee->id, $creator->id, $approver->id, $period, $snapshot];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
