<?php

namespace Modules\Payroll\Domain\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Application\Commands\CreatePayrollAdjustment\CreatePayrollAdjustmentCommand;
use Modules\Payroll\Application\Commands\ProcessPayrollAdjustment\ProcessPayrollAdjustmentCommand;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final readonly class PayrollAdjustmentBrowserFixtureService
{
    public function __construct(
        private ConnectionInterface $db,
        private PayrollRunService $runs,
        private PayrollAdjustmentManagementService $adjustments,
        private PayrollAdjustmentDownstreamService $downstream,
    ) {}

    public function assertSafeEnvironment(string $environment, string $database): void
    {
        if (! in_array($environment, ['local', 'testing'], true) || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException('Adjustment browser fixture is restricted to local/testing on a *_testing database.');
        }
    }

    /** @return array{company_id:int,user_id:int,email:string,unauthorized_email:string,password:string,adjustment_id:int,adjustment_number:string,statement_id:int} */
    public function create(string $environment, string $scenario): array
    {
        $database = (string) $this->db->getDatabaseName();
        $this->assertSafeEnvironment($environment, $database);
        if (! in_array($scenario, ['positive', 'negative'], true)) {
            throw new RuntimeException('Fixture scenario must be positive or negative.');
        }
        $code = $this->companyCode($scenario);
        if (Company::withTrashed()->where('code', $code)->exists()) {
            throw new RuntimeException('Fixture already exists. Run --cleanup for this scenario first.');
        }

        $password = 'BrowserAdjustment!2027';
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => 'Schema Adjustment Browser Fixture '.ucfirst($scenario), 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $maker = User::query()->create(['name' => 'Adjustment Browser Operator', 'email' => 'browser.adjustment.'.$scenario.'@example.test', 'email_verified_at' => now(), 'password' => Hash::make($password)]);
        $checker = User::query()->create(['name' => 'Adjustment Browser Checker', 'email' => 'browser.adjustment.checker.'.$scenario.'@example.test', 'email_verified_at' => now(), 'password' => Hash::make($password)]);
        $unauthorized = User::query()->create(['name' => 'Adjustment Browser Unauthorized', 'email' => 'browser.adjustment.unauthorized.'.$scenario.'@example.test', 'email_verified_at' => now(), 'password' => Hash::make($password)]);
        foreach ([$maker, $checker, $unauthorized] as $fixtureUser) {
            $fixtureUser->forceFill(['email_verified_at' => now()])->save();
        }
        $permissions = ['payroll.adjustment.view', 'payroll.adjustment.create', 'payroll.adjustment.update', 'payroll.adjustment.calculate', 'payroll.adjustment.review', 'payroll.adjustment.approve', 'payroll.adjustment.apply', 'payroll.adjustment.payment.create', 'payroll.adjustment.accounting.create', 'payroll.adjustment.statement.generate', 'payroll.adjustment.statement.download', 'payroll.adjustment.downstream.retry', 'payroll.payment.view', 'payroll.accounting.view'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $maker->givePermissionTo($permissions);
        $checker->givePermissionTo($permissions);
        foreach ([$maker, $checker, $unauthorized] as $user) {
            $this->db->table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        }
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $maker->id, 'employee_code' => 'BROWSER-ADJ-'.$scenario, 'full_name' => 'Adjustment Browser Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $period = (int) $this->db->table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => 'BROWSER-ADJ-2026-07-'.$scenario, 'name' => 'Adjustment Browser Historical Period', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = (int) $this->db->table('payroll_input_snapshots')->insertGetId(['company_id' => $company->id, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $maker->id, 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->db->table('payroll_employee_inputs')->insert(['company_id' => $company->id, 'payroll_input_snapshot_id' => $snapshot, 'employee_id' => $employee->id, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $component = (int) $this->db->table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => 'BROWSER-BASIC-'.$scenario, 'name' => 'Browser Basic Salary', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'display_order' => 1, 'status' => 'active', 'version' => 1, 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->db->table('employee_salary_component_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $component, 'amount_minor' => 5_000_000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $run = $this->runs->create($company->id, $period, $snapshot, 'BROWSER-ADJ-RUN-'.$scenario, 'VND', (string) Str::uuid(), $maker->id);
        $run = $this->runs->calculate($company->id, $run->id, 1, (string) Str::uuid(), $maker->id);
        $run = $this->runs->transition($company->id, $run->id, PayrollRunStatus::Submitted, 2, (string) Str::uuid(), $maker->id);
        $run = $this->runs->transition($company->id, $run->id, PayrollRunStatus::Approved, 3, (string) Str::uuid(), $checker->id);
        $run = $this->runs->transition($company->id, $run->id, PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $checker->id);
        $item = $this->integer($this->db->table('payroll_items')->where('payroll_run_id', $run->id)->value('id'));
        $reason = (int) $this->db->table('payroll_adjustment_reasons')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'code' => 'BROWSER-FIXTURE', 'name' => 'Browser fixture retroactive correction', 'category' => 'earning_correction', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $adjustment = $this->adjustments->create(new CreatePayrollAdjustmentCommand($company->id, $item, $reason, 'earning_correction', '2026-07-31', $period, 'browser-fixture', null, (string) Str::uuid(), $maker->id));
        $corrected = $scenario === 'positive' ? 5_250_000 : 4_750_000;
        $this->adjustments->process(new ProcessPayrollAdjustmentCommand($company->id, $adjustment, 'calculate', $maker->id, null, $corrected, 0, 0, 0, $corrected));
        foreach (['validate', 'submit', 'review'] as $action) {
            $this->adjustments->process(new ProcessPayrollAdjustmentCommand($company->id, $adjustment, $action, $maker->id));
        }
        $this->adjustments->process(new ProcessPayrollAdjustmentCommand($company->id, $adjustment, 'approve', $checker->id));
        $this->adjustments->process(new ProcessPayrollAdjustmentCommand($company->id, $adjustment, 'apply', $checker->id));
        $this->paymentAccount($company->id, $employee->id, $maker->id, $checker->id);
        $this->accountingMappings($company->id, $maker->id, $scenario);
        $this->downstream->executeAll($company->id, $adjustment, $checker->id);
        $number = $this->string($this->db->table('payroll_adjustment_requests')->where('id', $adjustment)->value('adjustment_number'));
        $statement = $this->integer($this->db->table('payroll_adjustment_payslips')->where('adjustment_request_id', $adjustment)->value('id'));

        return ['company_id' => $company->id, 'user_id' => $maker->id, 'email' => $maker->email, 'unauthorized_email' => $unauthorized->email, 'password' => $password, 'adjustment_id' => $adjustment, 'adjustment_number' => $number, 'statement_id' => $statement];
    }

    /** @return array{companies:int,users:int,files:int} */
    public function cleanup(string $environment, ?string $scenario = null): array
    {
        $this->assertSafeEnvironment($environment, (string) $this->db->getDatabaseName());
        $codes = $scenario === null ? [$this->companyCode('positive'), $this->companyCode('negative')] : [$this->companyCode($scenario)];
        $companies = Company::withTrashed()->whereIn('code', $codes)->get(['id']);
        $companyCount = 0;
        $userCount = 0;
        $fileCount = 0;
        foreach ($companies as $company) {
            $companyId = (int) $company->id;
            $paths = $this->db->table('payroll_adjustment_payslips')->where('company_id', $companyId)->whereNotNull('storage_path')->pluck('storage_path');
            foreach ($paths as $path) {
                if (is_string($path) && Storage::disk('local')->delete($path)) {
                    $fileCount++;
                }
            }
            $userIds = $this->db->table('company_user_access')->where('company_id', $companyId)->pluck('user_id')->filter(fn (mixed $id): bool => is_numeric($id))->map(fn (mixed $id): int => (int) $id)->all();
            $this->deleteCompanyRows($companyId);
            $this->db->table('notifications')->whereIn('notifiable_id', $userIds)->delete();
            $this->db->table('company_user_access')->where('company_id', $companyId)->delete();
            $this->db->table('activity_log')->where('properties->company_id', $companyId)->delete();
            Employee::withTrashed()->where('company_id', $companyId)->forceDelete();
            $deletedUsers = User::query()->whereIn('id', $userIds)->delete();
            $userCount += is_numeric($deletedUsers) ? (int) $deletedUsers : 0;
            Company::withTrashed()->whereKey($companyId)->forceDelete();
            $companyCount++;
        }

        return ['companies' => $companyCount, 'users' => $userCount, 'files' => $fileCount];
    }

    private function deleteCompanyRows(int $companyId): void
    {
        foreach (['payroll_adjustment_access_logs', 'payroll_adjustment_histories', 'payroll_adjustment_downstream_intents', 'payroll_adjustment_payslips', 'payroll_accounting_access_logs', 'payroll_accounting_validation_issues', 'payroll_accounting_journal_sources', 'payroll_accounting_journal_lines', 'payroll_accounting_journals', 'payroll_payment_access_logs', 'payroll_payment_validation_issues', 'payroll_payment_batch_items', 'payroll_payment_batches', 'notification_deliveries'] as $table) {
            $this->db->table($table)->where('company_id', $companyId)->delete();
        }
        foreach (['payroll_adjustment_recoveries', 'payroll_adjustment_validation_issues', 'payroll_adjustment_components', 'payroll_adjustment_snapshots', 'payroll_adjustment_runs', 'payroll_adjustment_requests', 'payroll_adjustment_reasons', 'payroll_accounting_mappings', 'payroll_accounting_policies', 'accounting_accounts', 'employee_payment_accounts', 'payroll_validation_issues', 'payroll_item_components', 'payroll_items', 'payroll_run_histories', 'payroll_runs', 'employee_salary_component_assignments', 'salary_components', 'payroll_employee_inputs', 'payroll_input_snapshots', 'payroll_periods'] as $table) {
            $this->db->table($table)->where('company_id', $companyId)->delete();
        }
    }

    private function paymentAccount(int $companyId, int $employeeId, int $makerId, int $checkerId): void
    {
        $this->db->table('employee_payment_accounts')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'employee_id' => $employeeId, 'payment_method' => 'bank_transfer', 'bank_code' => 'SCHEMA', 'bank_name' => 'Schema Browser Bank', 'branch_code' => 'HCM', 'account_number_encrypted' => encrypt('001234567890'), 'account_number_last4' => '7890', 'account_holder_name' => 'ADJUSTMENT BROWSER EMPLOYEE', 'currency' => 'VND', 'is_primary' => true, 'verification_status' => 'verified', 'verified_by' => $checkerId, 'verified_at' => now(), 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $makerId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function accountingMappings(int $companyId, int $actorId, string $scenario): void
    {
        $expense = $this->account($companyId, $actorId, 'BROWSER-6100-'.$scenario, 'Adjustment expense', 'debit', 'expense');
        $payable = $this->account($companyId, $actorId, 'BROWSER-3340-'.$scenario, 'Adjustment payable', 'credit', 'liability');
        $policy = (int) $this->db->table('payroll_accounting_policies')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'code' => 'BROWSER-ADJ-'.$scenario, 'name' => 'Browser Adjustment Accounting', 'version' => 1, 'currency' => 'VND', 'aggregation_strategy' => 'employee', 'effective_from' => '2026-01-01', 'rounding_rule' => 'minor_unit', 'status' => 'active', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['adjustment_gross', 'GROSS_ADJUSTMENT', $expense, null], ['adjustment_net_pay', 'NET_PAY_ADJUSTMENT', null, $payable]] as [$type, $code, $debit, $credit]) {
            $this->db->table('payroll_accounting_mappings')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'accounting_policy_id' => $policy, 'source_type' => $type, 'source_code' => $code, 'debit_account_id' => $debit, 'credit_account_id' => $credit, 'priority' => 100, 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function account(int $companyId, int $actorId, string $code, string $name, string $balance, string $type): int
    {
        return (int) $this->db->table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $balance, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function companyCode(string $scenario): string
    {
        if (! in_array($scenario, ['positive', 'negative'], true)) {
            throw new RuntimeException('Fixture scenario must be positive or negative.');
        }

        return 'BROWSER-ADJUSTMENT-'.strtoupper($scenario);
    }

    private function integer(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new RuntimeException('Expected numeric browser fixture identifier.');
        }

        return (int) $value;
    }

    private function string(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Expected browser fixture reference.');
        }

        return $value;
    }
}
