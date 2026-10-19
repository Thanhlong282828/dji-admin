<?php

namespace Modules\Tax\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final readonly class TaxReportReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string, mixed> */
    public function read(int $companyId, ?int $branchId, string $from, string $to, string $currency, ?User $actor): array
    {
        $currency = strtoupper($currency);
        if (! Schema::hasTable('vat_policies') || ! Schema::hasTable('tax_transaction_evidence')) {
            return [
                'schema_ready' => false,
                'branch_id' => $branchId,
                'from' => $from,
                'to' => $to,
                'currency' => $currency,
                'totals' => ['output' => 0, 'input' => 0, 'net' => 0],
                'policies' => [],
                'usage' => [],
                'categories' => [], 'accounts' => [], 'accounting_policies' => [],
                'process_help' => $this->processHelp($companyId, $branchId, $actor, 0, 0),
            ];
        }
        $policies = $this->db->table('vat_policies as policy')->join('tax_categories as category', 'category.id', '=', 'policy.tax_category_id')->join('accounting_accounts as account', 'account.id', '=', 'policy.tax_account_id')->where('policy.company_id', $companyId)->orderBy('category.direction')->orderBy('category.code')->orderByDesc('policy.version')->get(['policy.id', 'policy.tax_category_id', 'policy.tax_account_id', 'policy.accounting_posting_policy_id', 'policy.policy_key', 'policy.version', 'policy.country_code', 'policy.rate_basis_points', 'policy.currency', 'policy.effective_from', 'policy.effective_to', 'policy.status', 'category.code as category_code', 'category.name as category_name', 'category.direction', 'account.code as account_code', 'account.name as account_name'])->map(function (object $row) use ($actor, $companyId): array {
            /** @var object{accounting_posting_policy_id:int} $row */
            $policy = get_object_vars($row);
            $policy['source_url'] = $this->allowedRoute($actor, 'payroll.accounting.view', 'admin.finance.accounting-policies', ['company_id' => $companyId, 'policy_id' => $row->accounting_posting_policy_id]);

            return $policy;
        })->all();
        $usage = $this->db->table('tax_transaction_evidence as evidence')->join('tax_categories as category', 'category.id', '=', 'evidence.tax_category_id')->join('vat_policies as policy', 'policy.id', '=', 'evidence.tax_policy_id')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'evidence.accounting_journal_id')->where('evidence.company_id', $companyId)->when($branchId !== null, static fn (Builder $query): Builder => $query->where('evidence.branch_id', $branchId))->whereBetween('evidence.posting_date', [$from, $to])->where('evidence.currency', $currency)->orderByDesc('evidence.posting_date')->get(['evidence.*', 'category.code as category_code', 'category.direction', 'policy.policy_key', 'policy.accounting_posting_policy_id', 'journal.journal_number'])->map(function (object $row) use ($companyId, $branchId, $actor): array {
            /** @var object{source_type:string,source_id:int,category_code:string,direction:string,policy_key:string,policy_version:int,accounting_posting_policy_id:int,rate_basis_points:int,taxable_amount_minor:int,tax_amount_minor:int,posting_date:string,journal_number:string,accounting_journal_id:int} $row */
            $sourceType = $row->source_type;
            $sourceId = $row->source_id;
            $sign = $sourceType === 'customer_credit_note' ? -1 : 1;

            return ['source_type' => $sourceType, 'source_id' => $sourceId, 'source_url' => $this->sourceUrl($actor, $companyId, $branchId, $sourceType, $sourceId), 'category' => $row->category_code, 'direction' => $row->direction, 'policy' => $row->policy_key.' · v'.$row->policy_version, 'policy_url' => $this->allowedRoute($actor, 'payroll.accounting.view', 'admin.finance.accounting-policies', ['company_id' => $companyId, 'policy_id' => $row->accounting_posting_policy_id]), 'rate_basis_points' => $row->rate_basis_points, 'taxable' => $sign * $row->taxable_amount_minor, 'tax' => $sign * $row->tax_amount_minor, 'is_reversal' => $sign < 0, 'date' => $row->posting_date, 'journal' => $row->journal_number, 'journal_url' => $this->allowedRoute($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.show', ['journal' => $row->accounting_journal_id, 'company_id' => $companyId])];
        })->all();
        $output = $this->aggregateMinor(collect($usage)->where('direction', 'output')->sum('tax'));
        $input = $this->aggregateMinor(collect($usage)->where('direction', 'input')->sum('tax'));

        $categories = $this->db->table('tax_categories')->where(['company_id' => $companyId, 'status' => 'active'])->orderBy('code')->get(['id', 'code', 'name', 'direction'])->map(fn (object $row): array => get_object_vars($row))->all();
        $accounts = $this->db->table('accounting_accounts')->where(['company_id' => $companyId, 'status' => 'active', 'allow_posting' => true])->orderBy('code')->get(['id', 'code', 'name', 'account_type'])->map(fn (object $row): array => get_object_vars($row))->all();
        $accountingPolicies = $this->db->table('accounting_posting_policies')->where(['company_id' => $companyId, 'status' => 'approved'])->orderBy('policy_key')->orderByDesc('version')->get(['id', 'policy_key', 'version', 'business_event', 'currency'])->map(fn (object $row): array => get_object_vars($row))->all();

        return ['schema_ready' => true, 'branch_id' => $branchId, 'from' => $from, 'to' => $to, 'currency' => $currency, 'totals' => ['output' => $output, 'input' => $input, 'net' => $output - $input], 'policies' => $policies, 'usage' => $usage, 'categories' => $categories, 'accounts' => $accounts, 'accounting_policies' => $accountingPolicies, 'process_help' => $this->processHelp($companyId, $branchId, $actor, count($policies), count($usage))];
    }

    private function aggregateMinor(mixed $value): int
    {
        return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) ? (int) $value : 0;
    }

    /** @return array<string, mixed> */
    private function processHelp(int $companyId, ?int $branchId, ?User $actor, int $policyCount, int $evidenceCount): array
    {
        $scope = array_filter(['company_id' => $companyId, 'branch_id' => $branchId]);
        $accountingUrl = $this->allowedRoute($actor, 'payroll.accounting.view', 'admin.finance.accounting-policies', $scope);
        $journalUrl = $this->allowedRoute($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.index', $scope);
        $workspaceUrl = $this->allowedRoute($actor, 'tax-policy.view', 'admin.finance.tax-workspace', $scope);

        return [
            'title' => 'Tax / VAT Policy Flow',
            'description' => 'Transaction → classification → effective tax policy → account mapping → immutable journal → report.',
            'owner' => 'Tax bounded context',
            'accounting_impact' => 'Output VAT creates tax liability; recoverable Input VAT is a separate debit mapped by approved policy.',
            'steps' => [
                $this->step('Transaction', 'Commerce / Procurement', 'Canonical transaction read model', 'Invoice or bill is the immutable business source.', 'No tax journal before approval and posting.', 'Tax Classification', $evidenceCount, $workspaceUrl),
                $this->step('Tax Classification', 'Tax', TaxPolicyResolver::class, 'Selects VAT_OUTPUT or VAT_INPUT by transaction type.', 'Determines tax direction without hard-coded accounts.', 'Tax Policy', $policyCount, $workspaceUrl),
                $this->step('Tax Policy', 'Tax', TaxPolicyResolver::class, 'Resolves tenant, country, currency and effective version.', 'Fails closed when no unique approved policy exists.', 'Accounting Policy', $policyCount, $workspaceUrl),
                $this->step('Accounting Policy', 'Finance', 'AccountingPostingPolicyResolver', 'Provides versioned debit and credit mappings.', 'Controls AR/AP, revenue/expense and VAT accounts.', 'Tax Journal', $policyCount, $accountingUrl),
                $this->step('Tax Journal', 'Finance', 'SourceAccountingPostingService', 'Persists balanced journal with source and policy lineage.', 'Posted entries are immutable; correction uses reversal.', 'Tax Report', $evidenceCount, $journalUrl),
                $this->step('Tax Report', 'Tax', self::class, 'Aggregates evidence by company, branch, period and currency.', 'Output VAT − Input VAT; no inferred zero when evidence is absent.', 'Government filing (outside current scope)', $evidenceCount, $workspaceUrl),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function step(string $title, string $owner, string $source, string $meaning, string $accounting, string $next, int $count, ?string $url): array
    {
        return ['title' => $title, 'owner' => $owner, 'source' => $source, 'meaning' => $meaning, 'accounting' => $accounting, 'next' => $next, 'has_operating_data' => $count > 0, 'evidence_count' => $count, 'url' => $url, 'link_label' => 'View source', 'empty_action' => 'Create from upstream workflow'];
    }

    /** @param array<string, int|string> $parameters */
    private function allowedRoute(?User $actor, string $permission, string $route, array $parameters): ?string
    {
        return ($actor?->can($permission) ?? false) && Route::has($route) ? route($route, $parameters) : null;
    }

    private function sourceUrl(?User $actor, int $companyId, ?int $branchId, string $sourceType, int $sourceId): ?string
    {
        $scope = array_filter(['company_id' => $companyId, 'branch_id' => $branchId]);

        return match ($sourceType) {
            'customer_invoice' => $this->allowedRoute($actor, 'sales.view', 'admin.sales.index', $scope + ['invoice_id' => $sourceId]),
            'customer_credit_note' => $this->allowedRoute($actor, 'sales.view', 'admin.sales.index', $scope + ['credit_note_id' => $sourceId]),
            'vendor_bill' => $this->allowedRoute($actor, 'vendor-bill.view', 'admin.procurement.payables', $scope + ['bill_id' => $sourceId]),
            'finance_expense' => $this->allowedRoute($actor, 'finance.operations.view', 'admin.finance.operations', $scope + ['page' => 'expenses', 'expense_id' => $sourceId]),
            default => null,
        };
    }
}
