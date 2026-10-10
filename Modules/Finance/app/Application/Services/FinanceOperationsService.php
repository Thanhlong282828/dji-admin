<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class FinanceOperationsService
{
    public function __construct(private ConnectionInterface $db, private SourceAccountingPostingService $accounting, private TreasuryService $treasury, private BudgetExposureService $budgetExposure, private AccountingPostingPolicyResolver $postingPolicies) {}

    /** @param array<string,mixed> $data */
    public function mutate(string $action, int $companyId, User $actor, array $data): int
    {
        return match ($action) {
            'account.create' => $this->account($companyId, $actor, $data),
            'expense-category.create' => $this->expenseCategory($companyId, $data), 'expense.create' => $this->expense($companyId, $actor, $data), 'expense.transition' => $this->expenseTransition($companyId, $actor, $data),
            'asset-category.create' => $this->assetCategory($companyId, $data), 'asset.create' => $this->asset($companyId, $actor, $data), 'asset.activate' => $this->activateAsset($companyId, $actor, $data), 'asset.transfer' => $this->transferAsset($companyId, $actor, $data), 'asset.depreciate' => $this->depreciate($companyId, $actor, $data),
            'budget.create' => $this->budget($companyId, $actor, $data), 'budget.line' => $this->budgetLine($companyId, $data), 'budget.approve' => $this->approveBudget($companyId, $actor, $data),
            default => throw ValidationException::withMessages(['action' => 'Finance operation không được hỗ trợ.']),
        };
    }

    /** @param array<string,mixed> $d */
    private function account(int $companyId, User $actor, array $d): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $d): int {
            $this->db->table('companies')->where('id', $companyId)->lockForUpdate()->firstOrFail(['id']);
            $code = strtoupper($this->text($d, 'code'));
            $existing = $this->db->table('accounting_accounts')->where(['company_id' => $companyId, 'code' => $code])->first();
            if ($existing !== null) {
                $existingData = get_object_vars($existing);
                foreach (['name', 'account_type', 'normal_balance', 'currency'] as $field) {
                    if (($existingData[$field] ?? null) !== ($field === 'currency' ? strtoupper($this->text($d, $field)) : $this->text($d, $field))) {
                        throw ValidationException::withMessages(['code' => 'ACCOUNTING_ACCOUNT_CODE_CONFLICT']);
                    }
                }

                return $this->int($existingData['id'] ?? null);
            }
            $type = $this->text($d, 'account_type');
            if (! in_array($type, ['asset', 'liability', 'equity', 'revenue', 'expense', 'contra_asset'], true)) {
                throw ValidationException::withMessages(['account_type' => 'ACCOUNTING_ACCOUNT_TYPE_INVALID']);
            }
            $normal = $this->text($d, 'normal_balance');
            if (! in_array($normal, ['debit', 'credit'], true)) {
                throw ValidationException::withMessages(['normal_balance' => 'ACCOUNTING_ACCOUNT_NORMAL_BALANCE_INVALID']);
            }

            return (int) $this->db->table('accounting_accounts')->insertGetId([
                'uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'parent_id' => null, 'code' => $code,
                'name' => $this->text($d, 'name'), 'account_type' => $type, 'normal_balance' => $normal,
                'currency' => strtoupper($this->text($d, 'currency')), 'allow_posting' => true, 'status' => 'active',
                'effective_from' => $this->text($d, 'effective_from'), 'effective_to' => null, 'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    /** @return array<string,mixed> */
    public function read(int $companyId, string $page, int $year): array
    {
        $common = ['page' => $page, 'branches' => $this->db->table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->orderBy('name')->get(), 'costCenters' => $this->db->table('cost_centers')->where('company_id', $companyId)->orderBy('name')->get(), 'vendors' => $this->db->table('procurement_vendors')->where(['company_id' => $companyId, 'status' => 'active'])->orderBy('name')->get()];
        if ($page === 'expenses') {
            return $common + ['categories' => $this->db->table('finance_expense_categories')->where('company_id', $companyId)->orderBy('name')->get(), 'expenses' => $this->db->table('finance_expenses as e')->join('branches as b', 'b.id', '=', 'e.branch_id')->join('finance_expense_categories as c', 'c.id', '=', 'e.expense_category_id')->where('e.company_id', $companyId)->select(['e.*', 'b.name as branch_name', 'c.name as category_name'])->orderByDesc('e.expense_date')->limit(100)->get()];
        }
        if ($page === 'assets') {
            $depreciation = $this->db->table('finance_depreciation_entries')->where('company_id', $companyId)->orderByDesc('period_month')->limit(100)->get();
            $depreciationTotals = $this->db->table('finance_depreciation_entries')->where('company_id', $companyId)->groupBy('asset_id')->selectRaw('asset_id, SUM(amount_minor) accumulated_minor, MAX(period_month) latest_period')->get()->keyBy('asset_id');
            $assets = $this->db->table('finance_assets as a')->join('branches as b', 'b.id', '=', 'a.branch_id')->join('finance_asset_categories as c', 'c.id', '=', 'a.asset_category_id')->leftJoin('cost_centers as cc', 'cc.id', '=', 'a.cost_center_id')->where('a.company_id', $companyId)->select(['a.*', 'b.name as branch_name', 'c.name as category_name', 'cc.code as cost_center_code', 'cc.name as cost_center_name'])->orderBy('a.asset_code')->get()->map(function (object $asset) use ($depreciationTotals): object {
                $total = $depreciationTotals->get($this->int($asset->id ?? null));
                $accumulated = $this->int($total->accumulated_minor ?? null);
                $asset->accumulated_minor = $accumulated;
                $asset->book_value_minor = max(0, $this->int($asset->acquisition_cost_minor ?? null) - $accumulated);
                $asset->latest_depreciation_period = $total->latest_period ?? null;

                return $asset;
            });

            return $common + ['categories' => $this->db->table('finance_asset_categories')->where('company_id', $companyId)->orderBy('name')->get(), 'assets' => $assets, 'depreciation' => $depreciation, 'assetMetrics' => ['count' => $assets->count(), 'active' => $assets->where('status', 'active')->count(), 'cost' => $assets->sum(fn (object $asset): int => $this->int($asset->acquisition_cost_minor ?? null)), 'accumulated' => $assets->sum(fn (object $asset): int => $this->int($asset->accumulated_minor ?? null)), 'book_value' => $assets->sum(fn (object $asset): int => $this->int($asset->book_value_minor ?? null))]];
        }
        $budgets = $this->db->table('finance_budgets')->where(['company_id' => $companyId, 'year' => $year])->orderByDesc('id')->get();
        $lines = $this->db->table('finance_budget_lines as l')->join('finance_budgets as b', 'b.id', '=', 'l.budget_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->leftJoin('branches as br', 'br.id', '=', 'l.branch_id')->where(['b.company_id' => $companyId, 'b.year' => $year, 'b.status' => 'approved'])->select(['l.*', 'b.name as budget_name', 'b.status as budget_status', 'a.code as account_code', 'a.name as account_name', 'br.name as branch_name'])->get();
        $actuals = $this->db->table('payroll_accounting_journal_lines as l')
            ->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')
            ->where('j.company_id', $companyId)
            ->whereIn('j.status', ['internally_posted', 'exported'])
            ->whereYear('j.posting_date', $year)
            ->whereIn('l.account_code_snapshot', ['1200', '5000', '5100', '5200', '5300'])
            ->groupByRaw('l.account_id, COALESCE(l.branch_id,j.branch_id,0), COALESCE(l.cost_center_id,0), MONTH(j.posting_date)')
            ->selectRaw('l.account_id, COALESCE(l.branch_id,j.branch_id,0) branch_id, COALESCE(l.cost_center_id,0) cost_center_id, MONTH(j.posting_date) month, SUM(CAST(l.debit_minor AS SIGNED))-SUM(CAST(l.credit_minor AS SIGNED)) actual_minor')
            ->get()
            ->keyBy(fn (object $row): string => $this->actualKey($row->account_id ?? null, $row->branch_id ?? null, $row->cost_center_id ?? null, $row->month ?? null));
        $commitments = $this->db->table('purchase_orders as po')->join('purchase_order_lines as l', 'l.purchase_order_id', '=', 'po.id')->where('po.company_id', $companyId)->whereIn('po.status', ['approved', 'sent', 'partially_received'])->whereYear('po.order_date', $year)->groupByRaw('l.account_id, po.branch_id, COALESCE(l.cost_center_id,0), MONTH(po.order_date)')->selectRaw('l.account_id, po.branch_id, COALESCE(l.cost_center_id,0) cost_center_id, MONTH(po.order_date) month, SUM((l.ordered_quantity-l.received_quantity)*l.unit_cost_minor) commitment_minor')->get()->keyBy(fn (object $row): string => $this->actualKey($row->account_id ?? null, $row->branch_id ?? null, $row->cost_center_id ?? null, $row->month ?? null));
        $rows = $lines->map(function (object $line) use ($actuals, $commitments): array {
            $budget = $this->int($line->amount_minor ?? null);
            $key = $this->actualKey($line->account_id ?? null, $line->branch_id ?? null, $line->cost_center_id ?? null, $line->month ?? null);
            $actual = $this->int($actuals->get($key)->actual_minor ?? null);
            $commitment = $this->int($commitments->get($key)->commitment_minor ?? null);

            $exposure = $this->budgetExposure->calculate($budget, $actual, $commitment);

            return ['line' => $line, 'budget' => $budget, 'commitment' => $commitment, 'actual' => $actual, 'consumed' => $exposure['consumed'], 'remaining' => $exposure['remaining'], 'variance_bp' => $budget === 0 ? 0 : intdiv($exposure['remaining'] * 10_000, $budget)];
        });

        return $common + ['year' => $year, 'budgets' => $budgets, 'accounts' => $this->db->table('accounting_accounts')->where('company_id', $companyId)->whereIn('code', ['1200', '5000', '5100', '5200', '5300'])->get(), 'rows' => $rows];
    }

    /** @param array<string,mixed> $d */
    private function expenseCategory(int $c, array $d): int
    {
        return (int) $this->db->table('finance_expense_categories')->insertGetId(['company_id' => $c, 'code' => $this->text($d, 'code'), 'name' => $this->text($d, 'name'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $d */
    private function assetCategory(int $c, array $d): int
    {
        return (int) $this->db->table('finance_asset_categories')->insertGetId(['company_id' => $c, 'code' => $this->text($d, 'code'), 'name' => $this->text($d, 'name'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $d */
    private function expense(int $c, User $u, array $d): int
    {
        $this->branch($c, $this->integer($d, 'branch_id'));
        $costCenter = $this->costCenter($c, $this->nullableInt($d, 'cost_center_id'));

        $sourceKey = $this->nullableText($d, 'source_idempotency_key');
        if ($sourceKey !== null) {
            $existing = $this->db->table('finance_expenses')->where(['company_id' => $c, 'source_idempotency_key' => $sourceKey])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
        }

        return (int) $this->db->table('finance_expenses')->insertGetId(['company_id' => $c, 'source_type' => $this->nullableText($d, 'source_type'), 'source_id' => $this->nullableInt($d, 'source_id'), 'source_reference' => $this->nullableText($d, 'source_reference'), 'source_idempotency_key' => $sourceKey, 'branch_id' => $this->integer($d, 'branch_id'), 'cost_center_id' => $costCenter['id'], 'cost_center_code_snapshot' => $costCenter['code'], 'cost_center_name_snapshot' => $costCenter['name'], 'expense_category_id' => $this->integer($d, 'expense_category_id'), 'vendor_id' => $this->nullableInt($d, 'vendor_id'), 'reference' => $this->text($d, 'reference'), 'amount_minor' => $this->integer($d, 'amount_minor'), 'currency' => 'VND', 'expense_date' => $this->text($d, 'expense_date'), 'description' => $this->text($d, 'description'), 'status' => 'draft', 'payment_status' => 'unpaid', 'version' => 1, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $d */
    private function expenseTransition(int $c, User $u, array $d): int
    {
        $id = $this->integer($d, 'id');
        $to = $this->text($d, 'to');

        return $this->db->transaction(function () use ($c, $u, $d, $id, $to): int {
            $e = $this->db->table('finance_expenses')->where(['id' => $id, 'company_id' => $c])->lockForUpdate()->firstOrFail();
            $from = $this->rowText($e, 'status');
            $allowed = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'rejected'], 'approved' => ['paid']];
            if (! in_array($to, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Expense transition không hợp lệ.']);
            }if (in_array($to, ['approved', 'rejected'], true) && $this->rowInt($e, 'created_by') === $u->id) {
                throw ValidationException::withMessages(['actor' => 'Maker không được tự duyệt expense.']);
            }if ($to === 'rejected' && ! filled($d['decision_reason'] ?? null)) {
                throw ValidationException::withMessages(['decision_reason' => 'Lý do reject bắt buộc.']);
            }$updates = ['status' => $to, 'version' => $this->rowInt($e, 'version') + 1, 'updated_at' => now()];
            if (in_array($to, ['approved', 'rejected'], true)) {
                $updates += ['reviewed_by' => $u->id, 'reviewed_at' => now(), 'decision_reason' => $d['decision_reason'] ?? null];
            }if ($to === 'approved') {
                $date = $this->rowText($e, 'expense_date');
                $policy = $this->postingPolicies->resolve($c, 'OPERATING_EXPENSE', 'payable', $date, 'VND');
                $lines = $this->expensePolicyLines($policy, $this->rowInt($e, 'amount_minor'), $e, 'Operating expense');
                $updates['accounting_journal_id'] = $this->accounting->postPolicyBacked($c, $this->rowInt($e, 'branch_id'), 'finance_expense', $id, 'expense_incurred', $date, 'VND', $lines, $u->id, $policy['policy_id'], $policy['version']);
            }if ($to === 'paid') {
                $updates['payment_status'] = 'paid';
                $date = now()->toDateString();
                $policy = $this->postingPolicies->resolve($c, 'OPERATING_EXPENSE', 'cash', $date, 'VND');
                $lines = $this->expensePolicyLines($policy, $this->rowInt($e, 'amount_minor'), $e, 'Expense payment');
                $journal = $this->accounting->postPolicyBacked($c, $this->rowInt($e, 'branch_id'), 'finance_expense', $id, 'expense_payment', $date, 'VND', $lines, $u->id, $policy['policy_id'], $policy['version']);
                $updates['payment_journal_id'] = $journal;
                $this->treasury->recordSource($c, $this->rowInt($e, 'branch_id'), 'finance_expense_payment', $id, $journal, $this->rowText($e, 'reference'), $this->rowInt($e, 'amount_minor'), now()->toDateTimeString(), $u->id);
            } $this->db->table('finance_expenses')->where('id', $id)->update($updates);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    private function asset(int $c, User $u, array $d): int
    {
        $this->branch($c, $this->integer($d, 'branch_id'));
        $categoryId = $this->integer($d, 'asset_category_id');
        $this->db->table('finance_asset_categories')->where(['id' => $categoryId, 'company_id' => $c, 'status' => 'active'])->firstOrFail();
        $costCenter = $this->costCenter($c, $this->nullableInt($d, 'cost_center_id'));
        $vendorId = $this->nullableInt($d, 'vendor_id');
        if ($vendorId !== null) {
            $this->db->table('procurement_vendors')->where(['id' => $vendorId, 'company_id' => $c])->firstOrFail();
        }
        $acquisitionDate = CarbonImmutable::parse($this->text($d, 'acquisition_date'));
        $inServiceDate = CarbonImmutable::parse($this->text($d, 'in_service_date'));
        if ($inServiceDate->lt($acquisitionDate)) {
            throw ValidationException::withMessages(['in_service_date' => 'Ngày đưa vào sử dụng không được trước ngày mua.']);
        }
        $sourceKey = $this->nullableText($d, 'source_idempotency_key');
        if ($sourceKey !== null) {
            $existing = $this->db->table('finance_assets')->where(['company_id' => $c, 'source_idempotency_key' => $sourceKey])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
        }

        return (int) $this->db->table('finance_assets')->insertGetId(['company_id' => $c, 'source_type' => $this->nullableText($d, 'source_type'), 'source_id' => $this->nullableInt($d, 'source_id'), 'source_reference' => $this->nullableText($d, 'source_reference'), 'source_idempotency_key' => $sourceKey, 'asset_category_id' => $categoryId, 'branch_id' => $this->integer($d, 'branch_id'), 'cost_center_id' => $costCenter['id'], 'vendor_id' => $vendorId, 'asset_code' => $this->text($d, 'asset_code'), 'name' => $this->text($d, 'name'), 'acquisition_cost_minor' => $this->integer($d, 'acquisition_cost_minor'), 'residual_value_minor' => $this->integer($d, 'residual_value_minor', 0), 'currency' => 'VND', 'acquisition_date' => $acquisitionDate->toDateString(), 'in_service_date' => $inServiceDate->toDateString(), 'useful_life_months' => $this->integer($d, 'useful_life_months'), 'depreciation_method' => 'straight_line', 'status' => 'draft', 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $d */
    private function activateAsset(int $c, User $u, array $d): int
    {
        $id = $this->integer($d, 'id');

        return $this->db->transaction(function () use ($c, $u, $id): int {
            $a = $this->db->table('finance_assets')->where(['id' => $id, 'company_id' => $c])->lockForUpdate()->firstOrFail();
            if ($a->status === 'active') {
                return $id;
            }if ($a->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Chỉ asset draft được activate.']);
            }$amount = $this->rowInt($a, 'acquisition_cost_minor');
            $date = $this->rowText($a, 'acquisition_date');
            $currency = $this->rowText($a, 'currency');
            $policy = $this->postingPolicies->resolve($c, 'ASSET_ACQUISITION', null, $date, $currency);
            $lines = array_map(fn (array $line): array => ['account_code' => $line['account_code'], 'debit' => $line['entry_side'] === 'debit' ? $amount : 0, 'credit' => $line['entry_side'] === 'credit' ? $amount : 0, 'description' => 'Asset acquisition '.$line['line_role']], $policy['lines']);
            $j = $this->accounting->postPolicyBacked($c, $this->rowInt($a, 'branch_id'), 'finance_asset', $id, 'asset_acquisition', $date, $currency, $lines, $u->id, $policy['policy_id'], $policy['version']);
            $this->db->table('finance_assets')->where('id', $id)->update(['status' => 'active', 'acquisition_journal_id' => $j, 'updated_at' => now()]);
            $this->db->table('finance_asset_assignments')->insert(['company_id' => $c, 'asset_id' => $id, 'branch_id' => $a->branch_id, 'cost_center_id' => $a->cost_center_id, 'effective_from' => $a->in_service_date, 'reason' => 'Initial in-service assignment', 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    private function transferAsset(int $c, User $u, array $d): int
    {
        $id = $this->integer($d, 'id');
        $branch = $this->integer($d, 'branch_id');
        $this->branch($c, $branch);
        $date = $this->text($d, 'effective_date');

        return $this->db->transaction(function () use ($c, $u, $d, $id, $branch, $date): int {
            $a = $this->db->table('finance_assets')->where(['id' => $id, 'company_id' => $c, 'status' => 'active'])->lockForUpdate()->firstOrFail();
            $this->db->table('finance_asset_assignments')->where('asset_id', $id)->whereNull('effective_to')->update(['effective_to' => CarbonImmutable::parse($date)->subDay()->toDateString(), 'updated_at' => now()]);
            $this->db->table('finance_asset_assignments')->insert(['company_id' => $c, 'asset_id' => $id, 'branch_id' => $branch, 'cost_center_id' => $this->nullableInt($d, 'cost_center_id'), 'effective_from' => $date, 'reason' => $this->text($d, 'reason'), 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->db->table('finance_assets')->where('id', $id)->update(['branch_id' => $branch, 'cost_center_id' => $this->nullableInt($d, 'cost_center_id'), 'status' => 'transferred', 'updated_at' => now()]);
            $this->db->table('finance_assets')->where('id', $id)->update(['status' => 'active']);

            return $id;
        }, 3);
    }

    /** @param array<string,mixed> $d */
    private function depreciate(int $c, User $u, array $d): int
    {
        $id = $this->integer($d, 'id');
        $month = CarbonImmutable::parse($this->text($d, 'period_month'))->startOfMonth();

        return $this->db->transaction(function () use ($c, $u, $id, $month): int {
            $a = $this->db->table('finance_assets')->where(['id' => $id, 'company_id' => $c, 'status' => 'active'])->lockForUpdate()->firstOrFail();
            $existing = $this->db->table('finance_depreciation_entries')->where(['asset_id' => $id, 'period_month' => $month->toDateString()])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }if ($month->lt(CarbonImmutable::parse($this->rowText($a, 'in_service_date'))->startOfMonth())) {
                throw ValidationException::withMessages(['period' => 'Không depreciate trước in-service.']);
            }$acc = (int) $this->db->table('finance_depreciation_entries')->where('asset_id', $id)->sum('amount_minor');
            $base = max(0, $this->rowInt($a, 'acquisition_cost_minor') - $this->rowInt($a, 'residual_value_minor'));
            $monthly = intdiv($base, $this->rowInt($a, 'useful_life_months'));
            $amount = min($monthly, max(0, $base - $acc));
            if ($amount <= 0) {
                throw ValidationException::withMessages(['asset' => 'Asset đã depreciate hết giá trị.']);
            }$assignment = $this->assetAssignment($c, $id, $month->endOfMonth()->toDateString());
            $dimension = $this->costCenter($c, $this->nullableRowInt($assignment, 'cost_center_id'));
            $date = $month->endOfMonth()->toDateString();
            $currency = $this->rowText($a, 'currency');
            $policy = $this->postingPolicies->resolve($c, 'ASSET_DEPRECIATION', null, $date, $currency);
            $lines = array_map(function (array $line) use ($amount, $dimension): array {
                $mapped = ['account_code' => $line['account_code'], 'debit' => $line['entry_side'] === 'debit' ? $amount : 0, 'credit' => $line['entry_side'] === 'credit' ? $amount : 0, 'description' => 'Asset depreciation '.$line['line_role']];
                if ($line['requires_cost_center']) {
                    $mapped += ['cost_center_id' => $dimension['id'], 'cost_center_code_snapshot' => $dimension['code'], 'cost_center_name_snapshot' => $dimension['name']];
                }

                return $mapped;
            }, $policy['lines']);
            $j = $this->accounting->postPolicyBacked($c, $this->rowInt($assignment, 'branch_id'), 'finance_asset', $id, 'depreciation_'.$month->format('Ym'), $date, $currency, $lines, $u->id, $policy['policy_id'], $policy['version']);

            return (int) $this->db->table('finance_depreciation_entries')->insertGetId(['company_id' => $c, 'asset_id' => $id, 'cost_center_id' => $dimension['id'], 'cost_center_code_snapshot' => $dimension['code'], 'cost_center_name_snapshot' => $dimension['name'], 'period_month' => $month->toDateString(), 'amount_minor' => $amount, 'accumulated_minor' => $acc + $amount, 'book_value_minor' => $this->rowInt($a, 'acquisition_cost_minor') - $acc - $amount, 'status' => 'posted', 'accounting_journal_id' => $j, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    /** @param array<string,mixed> $d */
    private function budget(int $c, User $u, array $d): int
    {
        return (int) $this->db->table('finance_budgets')->insertGetId(['company_id' => $c, 'year' => $this->integer($d, 'year'), 'name' => $this->text($d, 'name'), 'status' => 'draft', 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $d */
    private function budgetLine(int $c, array $d): int
    {
        $budget = $this->db->table('finance_budgets')->where(['id' => $this->integer($d, 'budget_id'), 'company_id' => $c, 'status' => 'draft'])->firstOrFail();
        unset($budget);
        $branchId = $this->nullableInt($d, 'branch_id');
        if ($branchId !== null) {
            $this->branch($c, $branchId);
        }
        $costCenter = $this->costCenter($c, $this->nullableInt($d, 'cost_center_id'));
        $accountId = $this->integer($d, 'account_id');
        $this->db->table('accounting_accounts')->where(['id' => $accountId, 'company_id' => $c])->firstOrFail();

        return (int) $this->db->table('finance_budget_lines')->insertGetId(['budget_id' => $this->integer($d, 'budget_id'), 'branch_id' => $branchId, 'cost_center_id' => $costCenter['id'], 'account_id' => $accountId, 'month' => $this->integer($d, 'month'), 'amount_minor' => $this->integer($d, 'amount_minor'), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $d */
    private function approveBudget(int $c, User $u, array $d): int
    {
        $id = $this->integer($d, 'id');

        return $this->db->transaction(function () use ($c, $u, $id): int {
            // Serialize approval within a Company so two concurrent drafts cannot
            // become competing authorities for the same economic dimension.
            $this->db->table('finance_budgets')->where('company_id', $c)->lockForUpdate()->get(['id']);
            $budget = $this->db->table('finance_budgets')->where(['id' => $id, 'company_id' => $c, 'status' => 'draft'])->firstOrFail();
            if ($this->rowInt($budget, 'created_by') === $u->id) {
                throw ValidationException::withMessages(['actor' => 'Maker không được tự approve budget.']);
            }
            $overlap = $this->db->table('finance_budget_lines as candidate')
                ->join('finance_budgets as target', 'target.id', '=', 'candidate.budget_id')
                ->join('finance_budget_lines as existing', function (JoinClause $join): void {
                    $join->on('existing.account_id', '=', 'candidate.account_id');
                    $join->on('existing.month', '=', 'candidate.month');
                    $join->whereRaw('existing.branch_id <=> candidate.branch_id');
                    $join->whereRaw('existing.cost_center_id <=> candidate.cost_center_id');
                })
                ->join('finance_budgets as authority', 'authority.id', '=', 'existing.budget_id')
                ->where('candidate.budget_id', $id)->where('existing.budget_id', '<>', $id)
                ->whereColumn('authority.company_id', 'target.company_id')->whereColumn('authority.year', 'target.year')
                ->where('authority.status', 'approved')->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['budget' => 'Đã có Budget được duyệt cho cùng Company, kỳ, Branch, Cost Center và tài khoản.']);
            }
            $this->db->table('finance_budgets')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $u->id, 'approved_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    private function branch(int $c, int $b): void
    {
        $this->db->table('branches')->where(['id' => $b, 'company_id' => $c])->whereNull('deleted_at')->firstOrFail();
    }

    /** @return array{id:?int,code:?string,name:?string} */
    private function costCenter(int $companyId, ?int $costCenterId): array
    {
        if ($costCenterId === null) {
            return ['id' => null, 'code' => null, 'name' => null];
        }
        $center = $this->db->table('cost_centers')->where(['id' => $costCenterId, 'company_id' => $companyId])->firstOrFail(['id', 'code', 'name']);

        return ['id' => $this->rowInt($center, 'id'), 'code' => $this->rowText($center, 'code'), 'name' => $this->rowText($center, 'name')];
    }

    /** @return array{cost_center_id:?int,cost_center_code_snapshot:?string,cost_center_name_snapshot:?string} */
    private function expenseDimension(object $expense): array
    {
        return ['cost_center_id' => $this->nullableRowInt($expense, 'cost_center_id'), 'cost_center_code_snapshot' => $this->nullableRowText($expense, 'cost_center_code_snapshot'), 'cost_center_name_snapshot' => $this->nullableRowText($expense, 'cost_center_name_snapshot')];
    }

    /** @param array{lines:list<array{entry_side:string,line_role:string,account_code:string,requires_cost_center:bool}>} $policy @return list<array<string,mixed>> */
    private function expensePolicyLines(array $policy, int $amount, object $expense, string $description): array
    {
        return array_map(fn (array $line): array => [
            'account_code' => $line['account_code'],
            'debit' => $line['entry_side'] === 'debit' ? $amount : 0,
            'credit' => $line['entry_side'] === 'credit' ? $amount : 0,
            'description' => $description.' '.$line['line_role'],
            ...($line['requires_cost_center'] ? $this->expenseDimension($expense) : []),
        ], $policy['lines']);
    }

    private function assetAssignment(int $companyId, int $assetId, string $date): object
    {
        return $this->db->table('finance_asset_assignments')->where(['company_id' => $companyId, 'asset_id' => $assetId])->whereDate('effective_from', '<=', $date)->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->orderByDesc('effective_from')->firstOrFail();
    }

    private function nullableRowInt(object $row, string $property): ?int
    {
        $value = $row->{$property} ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableRowText(object $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string,mixed> $d */
    private function integer(array $d, string $k, int $default = -1): int
    {
        $v = $d[$k] ?? $default;

        return is_int($v) ? $v : (is_string($v) && ctype_digit($v) ? (int) $v : throw ValidationException::withMessages([$k => 'Giá trị số không hợp lệ.']));
    }

    /** @param array<string,mixed> $d */
    private function nullableInt(array $d, string $k): ?int
    {
        $v = $d[$k] ?? null;

        return $v === null || $v === '' ? null : $this->integer($d, $k);
    }

    /** @param array<string,mixed> $d */
    private function text(array $d, string $k): string
    {
        $v = $d[$k] ?? null;

        return is_string($v) && $v !== '' ? $v : throw ValidationException::withMessages([$k => 'Giá trị bắt buộc.']);
    }

    /** @param array<string,mixed> $d */
    private function nullableText(array $d, string $k): ?string
    {
        $value = $d[$k] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function int(mixed $v): int
    {
        return is_numeric($v) ? (int) $v : 0;
    }

    private function actualKey(mixed $accountId, mixed $branchId, mixed $costCenterId, mixed $month): string
    {
        return $this->int($accountId).'|'.$this->int($branchId).'|'.$this->int($costCenterId).'|'.$this->int($month);
    }

    private function rowInt(object $row, string $property): int
    {
        return $this->int($row->{$property} ?? null);
    }

    private function rowText(object $row, string $property): string
    {
        $value = $row->{$property} ?? null;
        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages([$property => 'Dữ liệu tài chính nguồn không hợp lệ.']);
        }

        return $value;
    }
}
