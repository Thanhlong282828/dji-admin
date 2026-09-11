<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingAdjustmentService;
use Modules\Finance\Application\Services\FinanceOperationsService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Tests\TestCase;

final class FinanceOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_asset_and_budget_share_posted_accounting_actuals(): void
    {
        [$maker, $checker, $company, $branch] = $this->runtime();
        $service = app(FinanceOperationsService::class);

        $expenseCategory = $service->mutate('expense-category.create', $company, $maker, ['code' => 'UTIL', 'name' => 'Chi phí tiện ích']);
        $expense = $service->mutate('expense.create', $company, $maker, ['branch_id' => $branch, 'expense_category_id' => $expenseCategory, 'reference' => 'EXP-202609-001', 'amount_minor' => 12_000_000, 'expense_date' => '2026-09-05', 'description' => 'Điện và Internet chi nhánh']);
        $service->mutate('expense.transition', $company, $maker, ['id' => $expense, 'to' => 'submitted']);
        $this->selfApprovalIsBlocked($service, $company, $maker, $expense);
        $service->mutate('expense.transition', $company, $checker, ['id' => $expense, 'to' => 'approved']);

        $expenseJournal = DB::table('payroll_accounting_journals')->where(['source_type' => 'finance_expense', 'source_id' => $expense])->firstOrFail();
        self::assertSame((int) $expenseJournal->total_debit_minor, (int) $expenseJournal->total_credit_minor);
        self::assertSame(12_000_000, (int) $expenseJournal->total_debit_minor);

        $assetCategory = $service->mutate('asset-category.create', $company, $maker, ['code' => 'IT', 'name' => 'Thiết bị CNTT']);
        $asset = $service->mutate('asset.create', $company, $maker, ['asset_category_id' => $assetCategory, 'branch_id' => $branch, 'asset_code' => 'AST-001', 'name' => 'Máy chủ chi nhánh', 'acquisition_cost_minor' => 120_000_000, 'residual_value_minor' => 0, 'acquisition_date' => '2026-09-01', 'in_service_date' => '2026-09-01', 'useful_life_months' => 60]);
        $service->mutate('asset.activate', $company, $maker, ['id' => $asset]);
        $depreciation = $service->mutate('asset.depreciate', $company, $maker, ['id' => $asset, 'period_month' => '2026-09-01']);
        self::assertSame($depreciation, $service->mutate('asset.depreciate', $company, $maker, ['id' => $asset, 'period_month' => '2026-09-01']));
        self::assertSame(2_000_000, (int) DB::table('finance_depreciation_entries')->where('id', $depreciation)->value('amount_minor'));
        self::assertSame(1, DB::table('finance_depreciation_entries')->where('asset_id', $asset)->count());

        $budget = $service->mutate('budget.create', $company, $maker, ['year' => 2026, 'name' => 'Operating Budget 2026']);
        $expenseAccount = (int) DB::table('accounting_accounts')->where(['company_id' => $company, 'code' => '5100'])->value('id');
        $service->mutate('budget.line', $company, $maker, ['budget_id' => $budget, 'branch_id' => $branch, 'account_id' => $expenseAccount, 'month' => 9, 'amount_minor' => 10_000_000]);
        $service->mutate('budget.approve', $company, $checker, ['id' => $budget]);
        $draft = $service->mutate('budget.create', $company, $maker, ['year' => 2026, 'name' => 'Draft is not authority']);
        $service->mutate('budget.line', $company, $maker, ['budget_id' => $draft, 'branch_id' => $branch, 'account_id' => $expenseAccount, 'month' => 10, 'amount_minor' => 99_000_000]);

        $rows = $service->read($company, 'budget', 2026)['rows'];
        self::assertCount(1, $rows);
        $row = $rows->first();
        self::assertSame(10_000_000, $row['budget']);
        self::assertSame(12_000_000, $row['actual']);
        self::assertSame(12_000_000, $row['consumed']);
        self::assertSame(-2_000_000, $row['remaining']);
        self::assertSame(-2_000, $row['variance_bp']);
    }

    public function test_posted_journal_is_reversed_by_idempotent_maker_checker_adjustment(): void
    {
        [$maker, $checker, $company, $branch] = $this->runtime();
        $finance = app(FinanceOperationsService::class);
        $category = $finance->mutate('expense-category.create', $company, $maker, ['code' => 'ADJ', 'name' => 'Chi phí cần điều chỉnh']);
        $expense = $finance->mutate('expense.create', $company, $maker, ['branch_id' => $branch, 'expense_category_id' => $category, 'reference' => 'EXP-ADJ-001', 'amount_minor' => 5_000_000, 'expense_date' => '2026-09-05', 'description' => 'Nghiệp vụ kiểm thử reversal']);
        $finance->mutate('expense.transition', $company, $maker, ['id' => $expense, 'to' => 'submitted']);
        $finance->mutate('expense.transition', $company, $checker, ['id' => $expense, 'to' => 'approved']);
        $original = DB::table('payroll_accounting_journals')->where(['source_type' => 'finance_expense', 'source_id' => $expense])->firstOrFail();
        $adjustments = app(AccountingAdjustmentService::class);
        $request = $adjustments->mutate('create', $company, $maker, ['original_journal_id' => $original->id, 'target_accounting_period_id' => $original->accounting_period_id, 'reference' => 'ADJ-001', 'reason' => 'Đảo bút toán sai kỳ bằng chứng']);
        $adjustments->mutate('submit', $company, $maker, ['id' => $request]);
        try {
            $adjustments->mutate('approve', $company, $maker, ['id' => $request]);
            self::fail('Maker must not approve their own accounting adjustment.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('approval', $exception->errors());
        }
        $adjustments->mutate('approve', $company, $checker, ['id' => $request]);
        $reversal = $adjustments->mutate('post', $company, $checker, ['id' => $request]);
        self::assertSame($reversal, $adjustments->mutate('post', $company, $checker, ['id' => $request]));
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'accounting_adjustment', 'source_id' => $request])->count());
        self::assertSame((int) $original->total_credit_minor, (int) DB::table('payroll_accounting_journals')->where('id', $reversal)->value('total_debit_minor'));
        self::assertSame((int) $original->total_debit_minor, (int) DB::table('payroll_accounting_journals')->where('id', $reversal)->value('total_credit_minor'));
        self::assertSame((int) $original->total_debit_minor, (int) DB::table('payroll_accounting_journals')->where('id', $original->id)->value('total_debit_minor'));
    }

    public function test_expense_and_depreciation_preserve_transaction_time_cost_center_without_dimensioning_liabilities(): void
    {
        [$maker, $checker, $company, $branch] = $this->runtime();
        $service = app(FinanceOperationsService::class);
        $centerA = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'CC-A', 'name' => 'Center A', 'created_at' => now(), 'updated_at' => now()]);
        $centerB = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'CC-B', 'name' => 'Center B', 'created_at' => now(), 'updated_at' => now()]);
        $category = $service->mutate('expense-category.create', $company, $maker, ['code' => 'CC', 'name' => 'Cost Center expense']);
        $expense = $service->mutate('expense.create', $company, $maker, ['branch_id' => $branch, 'cost_center_id' => $centerA, 'expense_category_id' => $category, 'reference' => 'EXP-CC-001', 'amount_minor' => 6_000_000, 'expense_date' => '2026-01-15', 'description' => 'Historical dimension proof']);
        DB::table('cost_centers')->where('id', $centerA)->update(['name' => 'Center A renamed']);
        $service->mutate('expense.transition', $company, $maker, ['id' => $expense, 'to' => 'submitted']);
        $service->mutate('expense.transition', $company, $checker, ['id' => $expense, 'to' => 'approved']);
        $expenseRow = DB::table('finance_expenses')->where('id', $expense)->firstOrFail();
        self::assertSame('Center A', $expenseRow->cost_center_name_snapshot);
        $expenseLines = DB::table('payroll_accounting_journal_lines')->where('journal_id', $expenseRow->accounting_journal_id)->orderBy('line_number')->get();
        self::assertSame($centerA, (int) $expenseLines[0]->cost_center_id);
        self::assertSame('Center A', $expenseLines[0]->cost_center_name_snapshot);
        self::assertNull($expenseLines[1]->cost_center_id);

        $assetCategory = $service->mutate('asset-category.create', $company, $maker, ['code' => 'CC-ASSET', 'name' => 'CC Asset']);
        $asset = $service->mutate('asset.create', $company, $maker, ['asset_category_id' => $assetCategory, 'branch_id' => $branch, 'cost_center_id' => $centerA, 'asset_code' => 'AST-CC-001', 'name' => 'Historical asset', 'acquisition_cost_minor' => 120_000_000, 'residual_value_minor' => 0, 'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-01', 'useful_life_months' => 60]);
        $service->mutate('asset.activate', $company, $maker, ['id' => $asset]);
        $january = $service->mutate('asset.depreciate', $company, $maker, ['id' => $asset, 'period_month' => '2026-01-01']);
        $service->mutate('asset.transfer', $company, $maker, ['id' => $asset, 'branch_id' => $branch, 'cost_center_id' => $centerB, 'effective_date' => '2026-06-01', 'reason' => 'Operational transfer']);
        $june = $service->mutate('asset.depreciate', $company, $maker, ['id' => $asset, 'period_month' => '2026-06-01']);
        self::assertSame($centerA, (int) DB::table('finance_depreciation_entries')->where('id', $january)->value('cost_center_id'));
        self::assertSame($centerB, (int) DB::table('finance_depreciation_entries')->where('id', $june)->value('cost_center_id'));
        self::assertSame($centerA, (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', DB::table('finance_depreciation_entries')->where('id', $january)->value('accounting_journal_id'))->where('account_code_snapshot', '5200')->value('cost_center_id'));
        self::assertSame($centerB, (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', DB::table('finance_depreciation_entries')->where('id', $june)->value('accounting_journal_id'))->where('account_code_snapshot', '5200')->value('cost_center_id'));
    }

    public function test_foreign_company_cost_center_is_rejected_for_expense_and_asset(): void
    {
        [$maker, , $company, $branch] = $this->runtime();
        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FINOPS-FOREIGN', 'name' => 'Foreign Finance Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreignCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $foreign, 'code' => 'FOREIGN', 'name' => 'Foreign', 'created_at' => now(), 'updated_at' => now()]);
        $service = app(FinanceOperationsService::class);
        $category = $service->mutate('expense-category.create', $company, $maker, ['code' => 'FOREIGN', 'name' => 'Foreign proof']);

        $this->expectException(RecordNotFoundException::class);
        $service->mutate('expense.create', $company, $maker, ['branch_id' => $branch, 'cost_center_id' => $foreignCenter, 'expense_category_id' => $category, 'reference' => 'EXP-FOREIGN', 'amount_minor' => 1, 'expense_date' => '2026-01-01', 'description' => 'Must fail']);
    }

    public function test_asset_creation_is_tenant_scoped_chronological_and_source_idempotent(): void
    {
        [$maker, , $company, $branch] = $this->runtime();
        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'ASSET-FOREIGN', 'name' => 'Foreign Asset Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreignCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $foreign, 'code' => 'FOREIGN-ASSET', 'name' => 'Foreign Asset Center', 'created_at' => now(), 'updated_at' => now()]);
        $service = app(FinanceOperationsService::class);
        $category = $service->mutate('asset-category.create', $company, $maker, ['code' => 'TENANT-ASSET', 'name' => 'Tenant Asset']);
        $data = ['asset_category_id' => $category, 'branch_id' => $branch, 'cost_center_id' => null, 'asset_code' => 'AST-IDEMPOTENT', 'name' => 'Idempotent asset', 'acquisition_cost_minor' => 36_000_000, 'residual_value_minor' => 0, 'acquisition_date' => '2026-08-20', 'in_service_date' => '2026-09-01', 'useful_life_months' => 36, 'source_idempotency_key' => 'AST-SOURCE-001'];
        $first = $service->mutate('asset.create', $company, $maker, $data);
        self::assertSame($first, $service->mutate('asset.create', $company, $maker, $data));
        self::assertSame(1, DB::table('finance_assets')->where(['company_id' => $company, 'source_idempotency_key' => 'AST-SOURCE-001'])->count());

        try {
            $service->mutate('asset.create', $company, $maker, [...$data, 'asset_code' => 'AST-FOREIGN-CENTER', 'source_idempotency_key' => 'AST-SOURCE-002', 'cost_center_id' => $foreignCenter]);
            self::fail('Foreign-company Cost Center must be rejected for Asset.');
        } catch (RecordNotFoundException) {
            self::assertFalse(DB::table('finance_assets')->where('asset_code', 'AST-FOREIGN-CENTER')->exists());
        }

        try {
            $service->mutate('asset.create', $company, $maker, [...$data, 'asset_code' => 'AST-BAD-DATE', 'source_idempotency_key' => 'AST-SOURCE-003', 'acquisition_date' => '2026-09-02', 'in_service_date' => '2026-09-01']);
            self::fail('In-service date before acquisition must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('in_service_date', $exception->errors());
        }
    }

    public function test_budget_lines_are_tenant_scoped_and_approved_authority_cannot_overlap(): void
    {
        [$maker, $checker, $company, $branch] = $this->runtime();
        $service = app(FinanceOperationsService::class);
        $account = app(SourceAccountingPostingService::class)->accountEvidence($company, '5100', 'VND', $maker->id)['id'];
        $first = $service->mutate('budget.create', $company, $maker, ['year' => 2026, 'name' => 'Authority A']);
        $service->mutate('budget.line', $company, $maker, ['budget_id' => $first, 'branch_id' => $branch, 'account_id' => $account, 'month' => 9, 'amount_minor' => 0]);
        $service->mutate('budget.approve', $company, $checker, ['id' => $first]);

        $second = $service->mutate('budget.create', $company, $maker, ['year' => 2026, 'name' => 'Authority B']);
        $service->mutate('budget.line', $company, $maker, ['budget_id' => $second, 'branch_id' => $branch, 'account_id' => $account, 'month' => 9, 'amount_minor' => 1]);
        try {
            $service->mutate('budget.approve', $company, $checker, ['id' => $second]);
            self::fail('Two approved Budgets must not own the same exact economic dimension.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('budget', $exception->errors());
        }
        self::assertSame('draft', DB::table('finance_budgets')->where('id', $second)->value('status'));

        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'BUDGET-FOREIGN', 'name' => 'Foreign Budget Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreignAccount = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $foreign, 'code' => '5100', 'name' => 'Foreign expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $third = $service->mutate('budget.create', $company, $maker, ['year' => 2027, 'name' => 'Tenant proof']);

        $this->expectException(RecordNotFoundException::class);
        $service->mutate('budget.line', $company, $maker, ['budget_id' => $third, 'branch_id' => $branch, 'account_id' => $foreignAccount, 'month' => 1, 'amount_minor' => 1]);
    }

    private function selfApprovalIsBlocked(FinanceOperationsService $service, int $company, User $maker, int $expense): void
    {
        try {
            $service->mutate('expense.transition', $company, $maker, ['id' => $expense, 'to' => 'approved']);
            self::fail('Expense maker must not approve the same expense.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('actor', $exception->errors());
        }
    }

    /** @return array{User,User,int,int} */
    private function runtime(): array
    {
        $maker = User::factory()->create(['email_verified_at' => now()]);
        $checker = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FINOPS', 'name' => 'Finance Operations Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'Trụ sở chính', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert([['company_id' => $company, 'user_id' => $maker->id, 'created_at' => now(), 'updated_at' => now()], ['company_id' => $company, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]]);

        return [$maker, $checker, $company, $branch];
    }
}
