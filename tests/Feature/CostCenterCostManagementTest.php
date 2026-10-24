<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\CostCenterCostManagementReadService;
use Tests\TestCase;

final class CostCenterCostManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_budget_actual_variance_and_unattributed_evidence_reconcile_without_double_counting(): void
    {
        [$company, $user] = $this->scope('A');
        $center = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'CC-A', 'name' => 'Operations A', 'created_at' => now(), 'updated_at' => now()]);
        $expense = $this->account($company, $user->id, '6100', 'Operating Expense', 'expense');
        $payable = $this->account($company, $user->id, '3310', 'Payable', 'liability');
        $budget = (int) DB::table('finance_budgets')->insertGetId(['company_id' => $company, 'year' => 2026, 'name' => 'Budget 2026', 'status' => 'approved', 'created_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('finance_budget_lines')->insert(['budget_id' => $budget, 'cost_center_id' => $center, 'account_id' => $expense, 'month' => 7, 'amount_minor' => 10000000, 'created_at' => now(), 'updated_at' => now()]);
        $journal = $this->journal($company, $user->id);
        $this->line($company, $journal, 1, $expense, 'earning_component', 5000000, 0, $center);
        $this->line($company, $journal, 2, $expense, 'employer_insurance_expense', 1000000, 0, $center);
        $this->line($company, $journal, 3, $expense, 'expense', 1000000, 0, $center);
        $this->line($company, $journal, 4, $expense, 'legacy_expense', 4000000, 0, null);
        $this->line($company, $journal, 5, $payable, 'payable', 0, 11000000, null);

        $data = app(CostCenterCostManagementReadService::class)->read($company, null, '2026-07-01', '2026-07-31');
        self::assertCount(1, $data['rows']);
        self::assertSame(10000000, $data['rows'][0]['budget']);
        self::assertSame(7000000, $data['rows'][0]['actual']);
        self::assertSame(5000000, $data['rows'][0]['payroll']);
        self::assertSame(1000000, $data['rows'][0]['employer_statutory']);
        self::assertSame(7000000, $data['rows'][0]['consumed']);
        self::assertSame(3000000, $data['rows'][0]['remaining']);
        self::assertSame(4000000, $data['totals']['unattributed_actual']);
        self::assertSame(11000000, $data['reconciliation']['company_expense']);
        self::assertSame(0, $data['reconciliation']['difference']);
        self::assertSame('PARTIAL', $data['reconciliation']['status']);
    }

    public function test_route_is_permission_scoped_filters_cost_center_and_rejects_cross_company_id(): void
    {
        [$company, $user] = $this->scope('ROUTE');
        [$foreign] = $this->scope('FOREIGN');
        $center = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'CC-ROUTE', 'name' => 'Route Center', 'created_at' => now(), 'updated_at' => now()]);
        $foreignCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $foreign, 'code' => 'CC-FOREIGN', 'name' => 'Foreign Center', 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('admin.finance.cost-center-costs', ['company_id' => $company]))->assertRedirect('/login');
        $this->actingAs($user)->get(route('admin.finance.cost-center-costs', ['company_id' => $company]))->assertForbidden();
        $user->givePermissionTo('finance.operations.view');
        $this->actingAs($user)->get(route('admin.finance.cost-center-costs', ['company_id' => $foreign]))->assertNotFound();
        $this->actingAs($user)->get(route('admin.finance.cost-center-costs', ['company_id' => $company, 'cost_center_id' => $foreignCenter]))->assertNotFound();
        $this->actingAs($user)->get(route('admin.finance.cost-center-costs', ['company_id' => $company, 'cost_center_id' => $center, 'date_from' => '2026-01-01', 'date_to' => '2026-12-31']))->assertOk()->assertSee('Chi phí theo Cost Center')->assertSee('Route Center')->assertSee('Revenue và COGS không được suy đoán');
    }

    public function test_inventory_realization_consumes_budget_without_becoming_recognized_expense(): void
    {
        [$company, $user] = $this->scope('INVENTORY');
        $center = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'CC-INV', 'name' => 'Inventory Operations', 'created_at' => now(), 'updated_at' => now()]);
        $inventory = $this->account($company, $user->id, '1200', 'Inventory', 'asset');
        $payable = $this->account($company, $user->id, '3310', 'Payable', 'liability');
        $budget = (int) DB::table('finance_budgets')->insertGetId(['company_id' => $company, 'year' => 2026, 'name' => 'Inventory Budget', 'status' => 'approved', 'created_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('finance_budget_lines')->insert(['budget_id' => $budget, 'cost_center_id' => $center, 'account_id' => $inventory, 'month' => 7, 'amount_minor' => 10_000_000, 'created_at' => now(), 'updated_at' => now()]);
        $journal = $this->journal($company, $user->id);
        $this->line($company, $journal, 1, $inventory, 'goods_receipt', 6_000_000, 0, $center);
        $this->line($company, $journal, 2, $payable, 'grni', 0, 6_000_000, null);

        $data = app(CostCenterCostManagementReadService::class)->read($company, null, '2026-07-01', '2026-07-31');

        self::assertSame(6_000_000, $data['rows'][0]['actual']);
        self::assertSame(6_000_000, $data['rows'][0]['inventory_acquisition']);
        self::assertSame(0, $data['rows'][0]['recognized_expense']);
        self::assertSame(6_000_000, $data['totals']['consumed']);
        self::assertSame(4_000_000, $data['totals']['remaining']);
        self::assertSame(0, $data['reconciliation']['company_expense']);
        self::assertSame(0, $data['reconciliation']['difference']);
    }

    public function test_query_count_does_not_grow_per_cost_center(): void
    {
        [$company] = $this->scope('QUERY');
        foreach (range(1, 20) as $index) {
            DB::table('cost_centers')->insert(['company_id' => $company, 'code' => 'CC-Q-'.$index, 'name' => 'Query '.$index, 'created_at' => now(), 'updated_at' => now()]);
        }
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });
        $data = app(CostCenterCostManagementReadService::class)->read($company, null, '2026-01-01', '2026-12-31');

        self::assertCount(20, $data['rows']);
        self::assertLessThanOrEqual(8, $queries);
    }

    /** @return array{int,User} */
    private function scope(string $suffix): array
    {
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'CCM-'.$suffix, 'name' => 'Cost Management '.$suffix, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$company, $user];
    }

    private function account(int $company, int $user, string $code, string $name, string $type): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $type === 'expense' ? 'debit' : 'credit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $user, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function journal(int $company, int $user): int
    {
        return (int) DB::table('payroll_accounting_journals')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'source_type' => 'finance_test', 'posting_type' => 'actual', 'journal_number' => 'CCM-'.Str::upper(Str::random(8)), 'journal_date' => '2026-07-31', 'posting_date' => '2026-07-31', 'resolution_date' => '2026-07-31', 'currency' => 'VND', 'calculation_version' => 1, 'status' => 'internally_posted', 'total_debit_minor' => 11000000, 'total_credit_minor' => 11000000, 'line_count' => 5, 'idempotency_key' => (string) Str::uuid(), 'version' => 1, 'created_by' => $user, 'posted_by' => $user, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function line(int $company, int $journal, int $number, int $account, string $type, int $debit, int $credit, ?int $costCenter): void
    {
        $accountRow = DB::table('accounting_accounts')->where('id', $account)->first(['code', 'name']);
        DB::table('payroll_accounting_journal_lines')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'journal_id' => $journal, 'line_number' => $number, 'source_type' => $type, 'source_code' => Str::upper($type), 'description' => $type, 'account_id' => $account, 'account_code_snapshot' => (string) ($accountRow?->code ?? ''), 'account_name_snapshot' => (string) ($accountRow?->name ?? ''), 'debit_minor' => $debit, 'credit_minor' => $credit, 'currency' => 'VND', 'cost_center_id' => $costCenter, 'cost_center_code_snapshot' => $costCenter === null ? null : 'CC-A', 'cost_center_name_snapshot' => $costCenter === null ? null : 'Operations A', 'source_count' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
