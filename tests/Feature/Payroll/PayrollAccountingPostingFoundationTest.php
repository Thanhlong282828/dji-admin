<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Services\PayrollRunService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PayrollAccountingPostingFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = ['payroll.accounting.view', 'payroll.accounting.policy.manage', 'payroll.accounting.mapping.manage', 'payroll.accounting.journal.create', 'payroll.accounting.journal.review', 'payroll.accounting.journal.approve', 'payroll.accounting.journal.post', 'payroll.accounting.journal.export', 'payroll.accounting.journal.download', 'payroll.accounting.journal.reverse'];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');
    }

    public function test_guest_permission_and_tenant_boundaries_are_enforced(): void
    {
        [$company, $maker, , $run] = $this->fixture('SEC');
        $payload = ['company_id' => $company, 'run_id' => $run, 'posting_date' => '2026-07-31', 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('admin.payroll.accounting.journals.store'), $payload)->assertRedirect('/login');
        $this->actingAs($maker)->post(route('admin.payroll.accounting.journals.store'), $payload)->assertForbidden();
        $maker->givePermissionTo('payroll.accounting.journal.create');
        $foreign = User::factory()->create();
        $foreign->givePermissionTo('payroll.accounting.journal.create');
        $this->actingAs($foreign)->post(route('admin.payroll.accounting.journals.store'), $payload)->assertForbidden();
        self::assertSame(0, DB::table('payroll_accounting_journals')->count());
    }

    public function test_account_policy_and_mapping_http_validation_are_operational(): void
    {
        [$company, $maker] = $this->fixture('ADMIN');
        $maker->givePermissionTo(['payroll.accounting.view', 'payroll.accounting.policy.manage', 'payroll.accounting.mapping.manage']);
        $this->actingAs($maker)->get(route('admin.payroll.accounting.accounts.index', ['company_id' => $company]))->assertOk()->assertSee('Chart of Accounts');
        $this->actingAs($maker)->post(route('admin.payroll.accounting.accounts.store'), $this->accountPayload($company, '6100-ADMIN', '=Payroll Expense', 'expense', 'debit'))->assertRedirect();
        $this->actingAs($maker)->post(route('admin.payroll.accounting.policies.store'), $this->policyPayload($company, 'POL-ADMIN'))->assertRedirect();
        $policy = $this->id('payroll_accounting_policies');
        $account = $this->id('accounting_accounts');
        $this->actingAs($maker)->post(route('admin.payroll.accounting.mappings.store'), $this->mappingPayload($company, $policy, 'earning_component', 'BASIC-ADMIN', $account, null))->assertRedirect();
        $this->actingAs($maker)->post(route('admin.payroll.accounting.mappings.store'), $this->mappingPayload($company, $policy, 'earning_component', 'BASIC-ADMIN', $account, null))->assertSessionHasErrors('effective_from');
    }

    public function test_exact_persisted_source_lifecycle_private_export_and_reversal(): void
    {
        [$company, $maker, $checker, $run, $employee] = $this->fixture('FLOW');
        $maker->givePermissionTo($this->permissions);
        $checker->givePermissionTo($this->permissions);
        [$expense, $payable, $policy] = $this->configuration($company, $maker, 'FLOW');
        $insuranceExpense = (int) DB::table('accounting_accounts')->insertGetId($this->accountRow($company, $maker->id, '6120-FLOW', 'Employer Insurance Expense', 'expense', 'debit'));
        $statutoryPayable = (int) DB::table('accounting_accounts')->insertGetId($this->accountRow($company, $maker->id, '3380-FLOW', 'Statutory Payable', 'liability', 'credit'));
        foreach ([['other_deduction', 'OTHER-FLOW', null, $statutoryPayable], ['employee_insurance', 'EMPLOYEE_INSURANCE', null, $statutoryPayable], ['employer_insurance_expense', 'EMPLOYER_INSURANCE_EXPENSE', $insuranceExpense, null], ['employer_insurance_liability', 'EMPLOYER_INSURANCE_LIABILITY', null, $statutoryPayable], ['personal_income_tax', 'PERSONAL_INCOME_TAX', null, $statutoryPayable]] as [$type, $code, $debit, $credit]) {
            DB::table('payroll_accounting_mappings')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'accounting_policy_id' => $policy, 'source_type' => $type, 'source_code' => $code, 'debit_account_id' => $debit, 'credit_account_id' => $credit, 'priority' => 100, 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $itemId = $this->id('payroll_items');
        DB::table('payroll_items')->where('id', $itemId)->update(['deduction_amount_minor' => 700000, 'insurance_employee_total_minor' => 500000, 'insurance_employer_total_minor' => 700000, 'personal_income_tax_minor' => 300000, 'net_after_personal_income_tax_minor' => 4000000, 'net_amount_minor' => 4000000]);
        DB::table('payroll_item_components')->insert(['company_id' => $company, 'payroll_run_id' => $run, 'payroll_item_id' => $itemId, 'component_code_snapshot' => 'OTHER-FLOW', 'component_name_snapshot' => 'Other deduction', 'category' => 'deduction', 'calculation_type' => 'fixed', 'source_type' => 'salary_component_assignment', 'amount_minor' => 200000, 'currency_code' => 'VND', 'calculation_order' => 2, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        self::assertGreaterThan(0, $expense + $payable + $policy);
        $key = (string) Str::uuid();
        $this->actingAs($maker)->post(route('admin.payroll.accounting.journals.store'), ['company_id' => $company, 'run_id' => $run, 'posting_date' => '2026-07-31', 'idempotency_key' => $key])->assertRedirect();
        $journal = $this->id('payroll_accounting_journals');
        $originalDebit = $this->int(DB::table('payroll_accounting_journals')->where('id', $journal)->value('total_debit_minor'));
        self::assertSame($originalDebit, $this->int(DB::table('payroll_accounting_journals')->where('id', $journal)->value('total_credit_minor')));
        self::assertSame(7, DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->count());
        foreach (DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->get() as $line) {
            $lineAmount = $this->int($line->debit_minor) + $this->int($line->credit_minor);
            self::assertSame($lineAmount, $this->int(DB::table('payroll_accounting_journal_sources')->where('journal_line_id', $line->id)->sum('source_amount_minor')));
        }
        self::assertSame($this->int(DB::table('payroll_items')->where('payroll_run_id', $run)->sum('net_after_personal_income_tax_minor')), $this->int(DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->where('source_type', 'net_pay')->sum('credit_minor')));
        self::assertSame(500000, $this->int(DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->where('source_type', 'employee_insurance')->sum('credit_minor')));
        self::assertSame(700000, $this->int(DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->where('source_type', 'employer_insurance_expense')->sum('debit_minor')));
        self::assertSame(700000, $this->int(DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->where('source_type', 'employer_insurance_liability')->sum('credit_minor')));
        self::assertSame(300000, $this->int(DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->where('source_type', 'personal_income_tax')->sum('credit_minor')));
        self::assertSame(200000, $this->int(DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->where('source_type', 'other_deduction')->sum('credit_minor')));
        $expenseSnapshot = $this->string(DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->where('source_type', 'earning_component')->value('account_name_snapshot'));
        DB::table('employees')->where('id', $employee)->update(['full_name' => 'Live changed after finalized source']);
        DB::table('employee_salary_component_assignments')->where('employee_id', $employee)->update(['amount_minor' => 999999999]);
        DB::table('accounting_accounts')->where('id', $expense)->update(['name' => 'Live account name changed']);
        DB::table('payroll_accounting_mappings')->where('accounting_policy_id', $policy)->update(['priority' => 9999]);
        self::assertSame($expenseSnapshot, DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal)->where('source_type', 'earning_component')->value('account_name_snapshot'));
        $this->actingAs($maker)->post(route('admin.payroll.accounting.journals.store'), ['company_id' => $company, 'run_id' => $run, 'posting_date' => '2026-08-01', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        self::assertSame(1, DB::table('payroll_accounting_journals')->where('payroll_run_id', $run)->count());
        self::assertSame($originalDebit, $this->int(DB::table('payroll_accounting_journals')->where('id', $journal)->value('total_debit_minor')));
        foreach (['validate', 'review'] as $action) {
            $this->actingAs($maker)->post(route('admin.payroll.accounting.journals.mutate', ['journal' => $journal, 'action' => $action]), ['company_id' => $company])->assertRedirect();
        }
        $this->actingAs($maker)->post(route('admin.payroll.accounting.journals.mutate', ['journal' => $journal, 'action' => 'approve']), ['company_id' => $company])->assertSessionHasErrors('actor');
        foreach (['approve', 'post', 'export'] as $action) {
            $this->actingAs($checker)->post(route('admin.payroll.accounting.journals.mutate', ['journal' => $journal, 'action' => $action]), ['company_id' => $company])->assertRedirect();
        }
        $stored = DB::table('payroll_accounting_journals')->where('id', $journal)->first();
        self::assertNotNull($stored);
        self::assertSame('exported', $stored->status);
        self::assertIsString($stored->storage_path);
        Storage::disk('local')->assertExists($stored->storage_path);
        $csv = Storage::disk('local')->get($stored->storage_path);
        self::assertIsString($csv);
        self::assertStringContainsString("'=Payroll Expense", $csv);
        self::assertSame(hash('sha256', $csv), $stored->checksum);
        self::assertSame('text/csv', $stored->mime_type);
        self::assertSame(strlen($csv), $this->int($stored->file_size));
        $this->actingAs($checker)->post(route('admin.payroll.accounting.journals.mutate', ['journal' => $journal, 'action' => 'export']), ['company_id' => $company])->assertRedirect();
        self::assertSame(1, DB::table('payroll_accounting_access_logs')->where('journal_id', $journal)->where('action', 'export')->count());
        $this->actingAs($checker)->get(route('admin.payroll.accounting.journals.download', ['journal' => $journal, 'company_id' => $company]))->assertOk()->assertHeader('cache-control', 'no-store, private');
        $this->actingAs($checker)->post(route('admin.payroll.accounting.journals.reverse', ['journal' => $journal]), ['company_id' => $company, 'reason' => 'Controlled correction', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $reversal = $this->int(DB::table('payroll_accounting_journals')->where('reversal_of_journal_id', $journal)->value('id'));
        self::assertSame($originalDebit, $this->int(DB::table('payroll_accounting_journals')->where('id', $reversal)->value('total_credit_minor')));
        self::assertSame(1, DB::table('payroll_accounting_access_logs')->where('journal_id', $journal)->where('action', 'download')->count());
    }

    public function test_non_finalized_run_inactive_account_and_missing_mapping_block_journal(): void
    {
        [$company, $maker, , $run] = $this->fixture('BLOCK');
        $maker->givePermissionTo('payroll.accounting.journal.create');
        [$expense, , $policy] = $this->configuration($company, $maker, 'BLOCK');
        $payload = ['company_id' => $company, 'run_id' => $run, 'posting_date' => '2026-07-31', 'idempotency_key' => (string) Str::uuid()];
        DB::table('payroll_accounting_mappings')->where('accounting_policy_id', $policy)->where('source_type', 'net_pay')->delete();
        $this->actingAs($maker)->post(route('admin.payroll.accounting.journals.store'), $payload)->assertSessionHasErrors('mapping');
        DB::table('accounting_accounts')->where('id', $expense)->update(['status' => 'inactive']);
        $this->actingAs($maker)->post(route('admin.payroll.accounting.journals.store'), $payload + ['idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('mapping');
        DB::table('payroll_runs')->where('id', $run)->update(['status' => 'approved']);
        $this->actingAs($maker)->post(route('admin.payroll.accounting.journals.store'), $payload + ['idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('run');
        self::assertSame(0, DB::table('payroll_accounting_journals')->where('company_id', $company)->count());
    }

    /** @return array{int,User,User,int,int} */
    private function fixture(string $token): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ACC-'.$token, 'name' => 'Schema Accounting '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        foreach ([$maker, $checker] as $u) {
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $u->id]);
        }
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'ACC-'.$token, 'full_name' => 'Accounting Employee '.$token, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $company->id, 'code' => 'ACC-'.$token, 'name' => 'Accounting Period', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $company->id, 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $maker->id, 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payroll_employee_inputs')->insert(['company_id' => $company->id, 'payroll_input_snapshot_id' => $snapshot, 'employee_id' => $employee->id, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $component = (int) DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => 'BASIC-'.$token, 'name' => 'Basic '.$token, 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'display_order' => 1, 'status' => 'active', 'version' => 1, 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_salary_component_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $component, 'amount_minor' => 5000000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(PayrollRunService::class);
        $run = $service->create($company->id, $period, $snapshot, 'RUN-'.$token, 'VND', (string) Str::uuid(), $maker->id);
        $run = $service->calculate($company->id, $run->id, 1, (string) Str::uuid(), $maker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Submitted, 2, (string) Str::uuid(), $maker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Approved, 3, (string) Str::uuid(), $checker->id);
        $run = $service->transition($company->id, $run->id, PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $checker->id);

        return [$company->id, $maker, $checker, $run->id, $employee->id];
    }

    /** @return array{int,int,int} */
    private function configuration(int $company, User $maker, string $token): array
    {
        $expense = (int) DB::table('accounting_accounts')->insertGetId($this->accountRow($company, $maker->id, '6100-'.$token, '=Payroll Expense', 'expense', 'debit'));
        $payable = (int) DB::table('accounting_accounts')->insertGetId($this->accountRow($company, $maker->id, '3340-'.$token, 'Payroll Payable', 'liability', 'credit'));
        $policy = (int) DB::table('payroll_accounting_policies')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => 'POL-'.$token, 'name' => 'Accounting Policy '.$token, 'version' => 1, 'currency' => 'VND', 'aggregation_strategy' => 'per_account_and_cost_center', 'effective_from' => '2026-01-01', 'rounding_rule' => 'half_up', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['earning_component', 'BASIC-'.$token, $expense, null], ['net_pay', 'NET_PAY', null, $payable]] as [$type, $code, $debit, $credit]) {
            DB::table('payroll_accounting_mappings')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'accounting_policy_id' => $policy, 'source_type' => $type, 'source_code' => $code, 'debit_account_id' => $debit, 'credit_account_id' => $credit, 'priority' => 100, 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return [$expense, $payable, $policy];
    }

    /** @return array<string,mixed> */
    private function accountPayload(int $c, string $code, string $name, string $type, string $balance): array
    {
        return ['company_id' => $c, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $balance, 'currency' => 'VND', 'allow_posting' => 1, 'status' => 'active', 'effective_from' => '2026-01-01'];
    }

    /** @return array<string,mixed> */
    private function policyPayload(int $c, string $code): array
    {
        return ['company_id' => $c, 'code' => $code, 'name' => 'Policy '.$code, 'version' => 1, 'currency' => 'VND', 'aggregation_strategy' => 'per_account_and_cost_center', 'rounding_rule' => 'half_up', 'status' => 'active', 'effective_from' => '2026-01-01'];
    }

    /** @return array<string,mixed> */
    private function mappingPayload(int $c, int $p, string $type, string $code, ?int $d, ?int $cr): array
    {
        return ['company_id' => $c, 'accounting_policy_id' => $p, 'source_type' => $type, 'source_code' => $code, 'debit_account_id' => $d, 'credit_account_id' => $cr, 'priority' => 100, 'effective_from' => '2026-01-01', 'status' => 'active'];
    }

    /** @return array<string,mixed> */
    private function accountRow(int $c, int $u, string $code, string $name, string $type, string $balance): array
    {
        return ['uuid' => (string) Str::uuid(), 'company_id' => $c, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $balance, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $u, 'created_at' => now(), 'updated_at' => now()];
    }

    private function id(string $table): int
    {
        return $this->int(DB::table($table)->orderByDesc('id')->value('id'));
    }

    private function int(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        } if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        } self::fail('Expected integer.');
    }

    private function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        self::fail('Expected string.');
    }
}
