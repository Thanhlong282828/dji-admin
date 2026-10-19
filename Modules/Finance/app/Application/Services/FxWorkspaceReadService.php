<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final readonly class FxWorkspaceReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function rates(int $companyId, User $actor, array $filters = []): array
    {
        $query = $this->db->table('finance_fx_rates')->where('company_id', $companyId)
            ->when(($filters['pair'] ?? '') !== '', function ($q) use ($filters): void {
                $pair = $filters['pair'] ?? null;
                [$base, $quote] = array_pad(explode('/', strtoupper(is_string($pair) ? $pair : ''), 2), 2, '');
                $q->where(['base_currency' => $base, 'quote_currency' => $quote]);
            })->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('rate_date')->orderByDesc('version');
        $rows = $query->paginate(25)->withQueryString();

        return ['company' => $this->companyContext($companyId, $actor), 'rates' => $rows, 'counts' => $this->db->table('finance_fx_rates')->where('company_id', $companyId)->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status'), 'process_help' => $this->processHelp('rate', $companyId, $actor)];
    }

    /** @return array<string,mixed> */
    public function exposure(int $companyId, User $actor): array
    {
        $companyCurrency = $this->db->table('companies')->where('id', $companyId)->value('functional_currency_code');
        $functional = is_string($companyCurrency) ? $companyCurrency : null;
        $ar = $this->db->table('customer_invoices')->where('company_id', $companyId)->whereIn('status', ['issued', 'partially_paid', 'overdue'])->whereRaw('total_minor > paid_minor')->when($functional !== null, fn ($q) => $q->where('currency', '<>', $functional))->selectRaw("'ar' source_type, id source_id, invoice_number reference, branch_id, currency, (total_minor-paid_minor) outstanding_minor")->get();
        $apAllocations = $this->db->table('ap_payment_allocations')->selectRaw('vendor_bill_id, SUM(allocated_minor) allocated_minor')->groupBy('vendor_bill_id');
        $ap = $this->db->table('vendor_bills as bill')->leftJoinSub($apAllocations, 'allocation', 'allocation.vendor_bill_id', '=', 'bill.id')->where('bill.company_id', $companyId)->whereIn('bill.status', ['approved', 'partially_paid', 'overdue'])->whereRaw('bill.total_minor > COALESCE(allocation.allocated_minor,0)')->when($functional !== null, fn ($q) => $q->where('bill.currency', '<>', $functional))->selectRaw("'ap' source_type, bill.id source_id, bill.invoice_number reference, bill.branch_id, bill.currency, (bill.total_minor-COALESCE(allocation.allocated_minor,0)) outstanding_minor")->get();
        $cash = $this->db->table('treasury_financial_accounts as account')->where('account.company_id', $companyId)->whereIn('account.type', ['cash', 'bank'])->when($functional !== null, fn ($q) => $q->where('account.currency', '<>', $functional))->selectRaw("'cash' source_type, account.id source_id, account.code reference, account.branch_id, account.currency, 0 outstanding_minor")->get();
        $rows = $ar->concat($ap)->concat($cash)->map(function (object $row) use ($actor, $companyId): array {
            /** @var object{source_type:string,source_id:int,reference:string,branch_id:int|null,currency:string,outstanding_minor:int} $row */
            $type = $row->source_type;
            $permission = ['ar' => 'sales.view', 'ap' => 'vendor-bill.view', 'cash' => 'treasury.view'][$type];
            $route = ['ar' => 'admin.sales.index', 'ap' => 'admin.procurement.payables', 'cash' => 'admin.finance.treasury'][$type];

            return ['source_type' => $type, 'source_id' => $row->source_id, 'reference' => $row->reference, 'branch_id' => $row->branch_id, 'currency' => $row->currency, 'outstanding_minor' => $row->outstanding_minor, 'source_url' => $actor->can($permission) ? route($route, array_filter(['company_id' => $companyId, 'branch_id' => $row->branch_id])) : null];
        })->groupBy('currency');

        return ['company' => $this->companyContext($companyId, $actor), 'functional_currency' => $functional, 'groups' => $rows, 'status' => $functional === null ? 'FUNCTIONAL_CURRENCY_REQUIRED' : 'SUPPORTED_SOURCE_DISCOVERY', 'process_help' => $this->processHelp('exposure', $companyId, $actor)];
    }

    /** @return array<string,mixed> */
    public function revaluations(int $companyId, User $actor): array
    {
        $lineTotals = $this->db->table('finance_fx_revaluation_lines')->selectRaw('fx_revaluation_run_id, COUNT(*) source_count, SUM(fx_difference_minor) difference_minor')->groupBy('fx_revaluation_run_id');
        $runs = $this->db->table('finance_fx_revaluation_runs as run')
            ->leftJoinSub($lineTotals, 'line_total', 'line_total.fx_revaluation_run_id', '=', 'run.id')
            ->leftJoin('accounting_periods as period', 'period.id', '=', 'run.accounting_period_id')
            ->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'run.accounting_journal_id')
            ->leftJoin('payroll_accounting_journals as reversal', 'reversal.id', '=', 'run.reversal_journal_id')
            ->where('run.company_id', $companyId)->orderByDesc('run.as_of_date')
            ->select('run.*', 'journal.journal_number', 'reversal.journal_number as reversal_journal_number', 'line_total.source_count', 'line_total.difference_minor')
            ->selectRaw("CONCAT(period.year, '-', LPAD(period.month, 2, '0')) as period_code")
            ->paginate(25)->withQueryString();

        $lines = $this->db->table('finance_fx_revaluation_lines as line')
            ->join('finance_fx_revaluation_runs as run', 'run.id', '=', 'line.fx_revaluation_run_id')
            ->leftJoin('customer_invoices as invoice', function (JoinClause $join): void {
                $join->on('invoice.id', '=', 'line.source_id')->where('line.source_type', '=', 'customer_invoice');
            })
            ->leftJoin('vendor_bills as bill', function (JoinClause $join): void {
                $join->on('bill.id', '=', 'line.source_id')->where('line.source_type', '=', 'vendor_bill');
            })
            ->where('line.company_id', $companyId)->whereIn('line.fx_revaluation_run_id', $runs->getCollection()->pluck('id'))
            ->orderBy('line.id')->get(['line.*', 'invoice.invoice_number', 'bill.invoice_number as supplier_invoice_number'])
            ->map(function (object $line) use ($actor, $companyId): array {
                /** @var object{id:int,fx_revaluation_run_id:int,source_type:string,source_id:int,invoice_number:string|null,supplier_invoice_number:string|null,transaction_currency:string,foreign_outstanding_minor:int,carrying_functional_minor:int,revalued_functional_minor:int,fx_difference_minor:int,mapping_snapshot:string} $line */
                $snapshot = json_decode((string) $line->mapping_snapshot, true);

                return [
                    'id' => $line->id, 'run_id' => $line->fx_revaluation_run_id,
                    'source_type' => $line->source_type, 'source_id' => $line->source_id,
                    'reference' => $line->invoice_number ?? ($line->supplier_invoice_number ?? strtoupper($line->source_type).' #'.$line->source_id),
                    'transaction_currency' => $line->transaction_currency,
                    'foreign_outstanding_minor' => $line->foreign_outstanding_minor,
                    'carrying_functional_minor' => $line->carrying_functional_minor,
                    'revalued_functional_minor' => $line->revalued_functional_minor,
                    'fx_difference_minor' => $line->fx_difference_minor,
                    'source_url' => match (true) {
                        $line->source_type === 'customer_invoice' && $actor->can('sales.view') => route('admin.sales.index', ['company_id' => $companyId, 'invoice_id' => $line->source_id]),
                        $line->source_type === 'vendor_bill' && $actor->can('vendor-bill.view') => route('admin.procurement.payables', ['company_id' => $companyId, 'vendor_bill_id' => $line->source_id]).'#vendor-bill-'.$line->source_id,
                        default => null,
                    },
                    'evidence' => is_array($snapshot) ? ($snapshot['source_evidence'] ?? []) : [],
                ];
            })->groupBy('run_id');

        $periods = $this->db->table('accounting_periods')->where(['company_id' => $companyId, 'status' => 'open'])
            ->orderByDesc('end_date')->get(['id', 'year', 'month', 'start_date', 'end_date']);
        $branches = $this->db->table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->orderBy('name')->get(['id', 'code', 'name']);

        return ['company' => $this->companyContext($companyId, $actor), 'runs' => $runs, 'lines' => $lines, 'periods' => $periods, 'branches' => $branches, 'create_idempotency_key' => (string) Str::uuid(), 'permissions' => ['manage' => $actor->can('finance.fx-revaluation.manage'), 'approve' => $actor->can('finance.fx-revaluation.approve'), 'post' => $actor->can('finance.fx-revaluation.post'), 'journal' => $actor->can('payroll.accounting.view')], 'process_help' => $this->processHelp('revaluation', $companyId, $actor)];
    }

    /** @return array<string,mixed> */
    private function processHelp(string $type, int $companyId, User $actor): array
    {
        $labels = match ($type) {
            'rate' => ['Functional Currency', 'Create Rate', 'Submit', 'Approve', 'Activate', 'Resolve Rate', 'Immutable Snapshot'],
            'exposure' => ['Functional Currency', 'Source Transaction', 'Currency Evidence', 'Open Position', 'Revaluation Eligibility'],
            default => ['AR/AP Recognition', 'As-of Collection/Payment Evidence', 'Open Foreign Balance', 'Functional Carrying Basis', 'Closing Rate', 'Immutable Snapshot', 'Unrealized Revaluation', 'Approval', 'Policy-backed Journal', 'Reversal'],
        };
        $route = 'admin.finance.fx-'.$type;
        $url = Route::has($route) && $actor->can('finance.fx-'.$type.'.view') ? route($route, ['company_id' => $companyId]) : null;

        return ['title' => 'Finance FX '.ucfirst($type), 'description' => 'Canonical Finance-owned multi-currency governance.', 'owner' => 'Finance', 'accounting_impact' => $type === 'revaluation' ? 'Policy-backed journal only when evidence is complete.' : 'No journal is inferred.', 'steps' => array_map(fn (string $label): array => ['title' => $label, 'owner' => 'Finance', 'source' => self::class, 'meaning' => 'Governed FX lifecycle step.', 'accounting' => $type === 'revaluation' ? 'Policy-backed journal only when evidence is complete.' : 'No journal is inferred.', 'next' => 'Next governed step', 'url' => $url, 'has_operating_data' => false, 'evidence_count' => 0, 'link_label' => 'Open canonical workspace', 'empty_action' => 'Open canonical workspace'], $labels)];
    }

    /** @return array<string,mixed> */
    private function companyContext(int $companyId, User $actor): array
    {
        $company = $this->db->table('companies')->where('id', $companyId)->first(['id', 'code', 'name', 'currency_code', 'functional_currency_code']);
        abort_if($company === null, 404);
        /** @var object{id:int,code:string,name:string,currency_code:string,functional_currency_code:string|null} $company */

        return [
            'id' => $company->id,
            'code' => $company->code,
            'name' => $company->name,
            'default_currency' => $company->currency_code,
            'functional_currency' => is_string($company->functional_currency_code) ? $company->functional_currency_code : null,
            'source_url' => Route::has('admin.companies.show') && $actor->can('company.view') ? route('admin.companies.show', $companyId) : null,
        ];
    }
}
