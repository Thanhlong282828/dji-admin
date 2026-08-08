<?php

namespace Tests\Support\Payroll;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Services\PayrollRunService;

trait CreatesAppliedPayrollAdjustment
{
    /** @return array{company:int,maker:User,checker:User,employee:int,run:int,item:int,period:int,reason:int} */
    protected function payrollAdjustmentFixture(string $token): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'C2-'.$token.'-'.Str::upper(Str::random(3)), 'name' => 'Schema C2 '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        foreach ([$maker, $checker] as $user) {
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        }
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'C2-'.$token, 'full_name' => 'C2 Employee '.$token, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => 'C2-'.$token, 'name' => 'Historical Period', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $company->id, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $maker->id, 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payroll_employee_inputs')->insert(['company_id' => $company->id, 'payroll_input_snapshot_id' => $snapshot, 'employee_id' => $employee->id, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $component = (int) DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => 'BASIC-'.$token, 'name' => 'Basic salary', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'display_order' => 1, 'status' => 'active', 'version' => 1, 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_salary_component_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $component, 'amount_minor' => 5_000_000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(PayrollRunService::class);
        $run = $service->create($company->id, $period, $snapshot, 'RUN-'.$token, 'VND', (string) Str::uuid(), $maker->id);
        $run = $service->calculate($company->id, $run->id, 1, (string) Str::uuid(), $maker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Submitted, 2, (string) Str::uuid(), $maker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Approved, 3, (string) Str::uuid(), $checker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $checker->id);
        $item = $this->databaseInt(DB::table('payroll_items')->where('payroll_run_id', $run->id)->value('id'));
        $reason = (int) DB::table('payroll_adjustment_reasons')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'code' => 'C2-'.$token, 'name' => 'Approved retroactive correction', 'category' => 'earning_correction', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);

        return ['company' => $company->id, 'maker' => $maker, 'checker' => $checker, 'employee' => $employee->id, 'run' => $run->id, 'item' => $item, 'period' => $period, 'reason' => $reason];
    }

    /** @param array{company:int,maker:User,checker:User,employee:int,run:int,item:int,period:int,reason:int} $fixture */
    protected function applyAdjustment(array $fixture, int $corrected): int
    {
        $permissions = ['payroll.adjustment.view', 'payroll.adjustment.create', 'payroll.adjustment.update', 'payroll.adjustment.calculate', 'payroll.adjustment.review', 'payroll.adjustment.approve', 'payroll.adjustment.apply', 'payroll.adjustment.payment.create', 'payroll.adjustment.accounting.create', 'payroll.adjustment.statement.generate', 'payroll.adjustment.statement.download', 'payroll.adjustment.downstream.retry'];
        $fixture['maker']->givePermissionTo($permissions);
        $fixture['checker']->givePermissionTo($permissions);
        $payload = ['company_id' => $fixture['company'], 'source_payroll_item_id' => $fixture['item'], 'adjustment_reason_id' => $fixture['reason'], 'adjustment_type' => 'earning_correction', 'requested_effective_date' => '2026-07-31', 'target_payment_period_id' => $fixture['period'], 'description' => 'C2 integration correction', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.adjustments.store'), $payload)->assertRedirect();
        $id = $this->databaseInt(DB::table('payroll_adjustment_requests')->max('id'));
        $correct = ['company_id' => $fixture['company'], 'corrected_gross_minor' => $corrected, 'corrected_employee_insurance_minor' => 0, 'corrected_employer_insurance_minor' => 0, 'corrected_personal_income_tax_minor' => 0, 'corrected_net_minor' => $corrected];
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'calculate']), $correct)->assertRedirect();
        foreach (['validate', 'submit', 'review'] as $action) {
            $this->actingAs($fixture['maker'])->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => $action]), ['company_id' => $fixture['company']])->assertRedirect();
        }
        $this->actingAs($fixture['checker'])->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'approve']), ['company_id' => $fixture['company']])->assertRedirect();
        $this->actingAs($fixture['checker'])->post(route('admin.payroll.adjustments.mutate', ['adjustment' => $id, 'action' => 'apply']), ['company_id' => $fixture['company']])->assertRedirect();

        return $id;
    }

    /** @param array{company:int,maker:User,checker:User,employee:int,run:int,item:int,period:int,reason:int} $fixture */
    protected function verifiedPaymentAccount(array $fixture): void
    {
        DB::table('employee_payment_accounts')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'employee_id' => $fixture['employee'], 'payment_method' => 'bank_transfer', 'bank_code' => 'SCHEMA', 'bank_name' => 'Schema Bank', 'branch_code' => 'HCM', 'account_number_encrypted' => Crypt::encryptString('001234567890'), 'account_number_last4' => '7890', 'account_holder_name' => 'C2 BENEFICIARY', 'currency' => 'VND', 'is_primary' => true, 'verification_status' => 'verified', 'verified_by' => $fixture['checker']->id, 'verified_at' => now(), 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array{company:int,maker:User,checker:User,employee:int,run:int,item:int,period:int,reason:int} $fixture */
    protected function adjustmentAccountingMappings(array $fixture): void
    {
        $expense = $this->account($fixture, '6100', 'Payroll expense', 'debit');
        $payable = $this->account($fixture, '3340', 'Payroll payable', 'credit');
        $policy = (int) DB::table('payroll_accounting_policies')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => 'ADJ', 'name' => 'Adjustment accounting', 'version' => 1, 'currency' => 'VND', 'aggregation_strategy' => 'employee', 'effective_from' => '2026-01-01', 'rounding_rule' => 'minor_unit', 'status' => 'active', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['adjustment_gross', 'GROSS_ADJUSTMENT', $expense, null], ['adjustment_net_pay', 'NET_PAY_ADJUSTMENT', null, $payable]] as [$type, $code, $debit, $credit]) {
            DB::table('payroll_accounting_mappings')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'accounting_policy_id' => $policy, 'source_type' => $type, 'source_code' => $code, 'debit_account_id' => $debit, 'credit_account_id' => $credit, 'priority' => 100, 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @param array{company:int,maker:User,checker:User,employee:int,run:int,item:int,period:int,reason:int} $fixture */
    protected function account(array $fixture, string $code, string $name, string $balance): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => $code, 'name' => $name, 'account_type' => 'expense', 'normal_balance' => $balance, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $fixture['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function databaseInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        } if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        } self::fail('Expected integer database value.');
    }

    protected function databaseString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        self::fail('Expected string database value.');
    }
}
