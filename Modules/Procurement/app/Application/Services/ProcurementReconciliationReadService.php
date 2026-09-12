<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Finance\Application\Services\CostCenterCostManagementReadService;

/** @phpstan-type ProcurementFilters array{date_from:string,date_to:string,branch_id:?int,cost_center_id:?int,vendor_id:?int,classification:?string,currency:string} */
final readonly class ProcurementReconciliationReadService
{
    public function __construct(private ConnectionInterface $db, private CostCenterCostManagementReadService $costCenters) {}

    /** @param ProcurementFilters $filters
     * @return array<string,mixed>
     */
    public function read(int $companyId, array $filters): array
    {
        $lines = $this->scope($companyId, $filters);
        $goods = (clone $lines)->where('l.procurement_classification', 'inventory')->selectRaw('COALESCE(SUM(l.line_total_minor),0) ordered, COALESCE(SUM(l.received_quantity*l.unit_cost_minor),0) received')->first();
        $services = (clone $lines)->where('l.procurement_classification', 'operating_expense')->selectRaw('COALESCE(SUM(l.line_total_minor),0) ordered')->first();

        $billLines = $this->db->table('vendor_bill_lines as bl')->join('vendor_bills as bill', 'bill.id', '=', 'bl.vendor_bill_id')->join('purchase_order_lines as l', 'l.id', '=', 'bl.purchase_order_line_id')->join('purchase_orders as po', 'po.id', '=', 'l.purchase_order_id')->whereNotIn('bill.status', ['draft', 'cancelled', 'rejected']);
        $this->apply($billLines, $companyId, $filters, 'po', 'l');
        $goodsBilled = $this->integer((clone $billLines)->where('l.procurement_classification', 'inventory')->sum('bl.line_total_minor'));
        $serviceBilled = $this->integer((clone $billLines)->where('l.procurement_classification', 'operating_expense')->sum('bl.line_total_minor'));

        $acceptances = $this->db->table('service_acceptance_lines as sal')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')->join('purchase_orders as po', 'po.id', '=', 'sa.purchase_order_id')->join('purchase_order_lines as l', 'l.id', '=', 'sal.purchase_order_line_id');
        $this->apply($acceptances, $companyId, $filters, 'po', 'l');
        $accepted = $this->integer((clone $acceptances)->where('sa.status', 'approved')->sum('sal.accepted_minor'));
        $reversed = $this->integer((clone $acceptances)->where('sa.status', 'reversed')->sum('sal.accepted_minor'));
        $reversalPending = (clone $acceptances)->where('sa.status', 'reversal_requested')->distinct()->count('sa.id');

        $eligibleBills = $this->eligibleBills($companyId, $filters);
        $apOriginal = $this->integer($this->db->table('vendor_bills as total_bill')->joinSub(clone $eligibleBills, 'eligible', 'eligible.id', '=', 'total_bill.id')->sum('eligible.total_minor'));
        $paid = $this->integer($this->db->table('ap_payment_allocations as allocation')->joinSub(clone $eligibleBills, 'eligible', 'eligible.id', '=', 'allocation.vendor_bill_id')->sum('allocation.allocated_minor'));
        $ap = $apOriginal - $paid;

        $liabilities = $this->db->table('payroll_accounting_journal_lines as line')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')->where('journal.company_id', $companyId)->whereIn('journal.status', ['internally_posted', 'exported'])->whereBetween('journal.posting_date', [$filters['date_from'], $filters['date_to']])->when($filters['branch_id'], fn (Builder $q, int $id): Builder => $q->where('journal.branch_id', $id))->whereIn('line.account_code_snapshot', ['2150', '2160'])->whereIn('journal.source_type', ['goods_receipt', 'service_acceptance', 'vendor_bill'])->selectRaw('line.account_code_snapshot code, SUM(CAST(line.credit_minor AS SIGNED)-CAST(line.debit_minor AS SIGNED)) balance')->groupBy('line.account_code_snapshot')->pluck('balance', 'code');
        $apGl = $this->apGeneralLedger($companyId, $filters);
        $liabilityDimensionsAvailable = $filters['cost_center_id'] === null && $filters['vendor_id'] === null;
        $goodsGrni = $filters['classification'] !== null && $filters['classification'] !== 'inventory' ? 0 : ($liabilityDimensionsAvailable ? $this->integer($liabilities->get('2150')) : null);
        $serviceAccrual = $filters['classification'] !== null && $filters['classification'] !== 'operating_expense' ? 0 : ($liabilityDimensionsAvailable ? $this->integer($liabilities->get('2160')) : null);

        $cost = $this->costCenters->read($companyId, $filters['cost_center_id'], $filters['date_from'], $filters['date_to']);
        $goodsOrdered = $this->field($goods, 'ordered');
        $goodsReceived = $this->field($goods, 'received');
        $serviceOrdered = $this->field($services, 'ordered');
        $approvedBudget = $cost['totals']['budget'];
        $openCommitment = $cost['totals']['commitment'];
        $actual = $cost['totals']['actual'];
        $hasBudget = collect($cost['rows'])->contains(static fn (array $row): bool => $row['status'] !== 'NO_BUDGET');
        $budgetScopeIsComplete = $filters['branch_id'] === null && $filters['vendor_id'] === null && $filters['classification'] === null;
        $budgetStatus = ! $budgetScopeIsComplete ? 'PARTIAL_EVIDENCE' : ($hasBudget ? ($cost['totals']['over_budget'] > 0 ? 'OVER_BUDGET' : 'WITHIN_BUDGET') : 'NO_BUDGET');

        return [
            'filters' => $filters,
            'goods' => ['ordered' => $goodsOrdered, 'received' => $goodsReceived, 'unreceived' => max(0, $goodsOrdered - $goodsReceived), 'billed' => $goodsBilled, 'unbilled_received' => max(0, $goodsReceived - $goodsBilled), 'grni' => $goodsGrni],
            'service' => ['ordered' => $serviceOrdered, 'accepted' => $accepted, 'reversed' => $reversed, 'net_accepted' => $accepted, 'unaccepted' => max(0, $serviceOrdered - $accepted), 'billed' => $serviceBilled, 'unbilled_accepted' => max(0, $accepted - $serviceBilled), 'accrual' => $serviceAccrual, 'reversal_pending' => $reversalPending],
            'ap' => ['original' => $apOriginal, 'paid' => $paid, 'outstanding' => $ap, 'gl' => $apGl],
            'budget' => ['approved' => $approvedBudget, 'open_commitment' => $openCommitment, 'actual' => $actual, 'consumed' => $cost['totals']['consumed'], 'remaining' => $hasBudget ? $cost['totals']['remaining'] : null, 'status' => $budgetStatus],
            'cost_center' => $cost,
            'exceptions' => ['awaiting_receipt' => (clone $lines)->where('l.procurement_classification', 'inventory')->whereColumn('l.received_quantity', '<', 'l.ordered_quantity')->distinct()->count('po.id'), 'awaiting_acceptance' => (clone $lines)->where('l.procurement_classification', 'operating_expense')->whereRaw('l.ordered_quantity > COALESCE((SELECT SUM(sal.accepted_quantity) FROM service_acceptance_lines sal JOIN service_acceptances sa ON sa.id=sal.service_acceptance_id WHERE sal.purchase_order_line_id=l.id AND sa.status="approved"),0)')->distinct()->count('po.id'), 'reversal_pending' => $reversalPending],
        ];
    }

    /** @param ProcurementFilters $filters */
    private function eligibleBills(int $companyId, array $filters): Builder
    {
        return $this->db->table('vendor_bills as bill')->join('purchase_orders as po', 'po.id', '=', 'bill.purchase_order_id')
            ->where('bill.company_id', $companyId)->whereNotIn('bill.status', ['draft', 'cancelled', 'rejected'])
            ->whereBetween('bill.invoice_date', [$filters['date_from'], $filters['date_to']])->where('bill.currency', $filters['currency'])
            ->when($filters['branch_id'], fn (Builder $query, int $id): Builder => $query->where('bill.branch_id', $id))
            ->when($filters['vendor_id'], fn (Builder $query, int $id): Builder => $query->where('bill.vendor_id', $id))
            ->when($filters['cost_center_id'] || $filters['classification'], function (Builder $query) use ($filters): void {
                $query->whereExists(function (Builder $lines) use ($filters): void {
                    $lines->selectRaw('1')->from('vendor_bill_lines as eligible_line')->join('purchase_order_lines as eligible_po_line', 'eligible_po_line.id', '=', 'eligible_line.purchase_order_line_id')->whereColumn('eligible_line.vendor_bill_id', 'bill.id')
                        ->when($filters['cost_center_id'], fn (Builder $inner, int $id): Builder => $inner->where('eligible_po_line.cost_center_id', $id))
                        ->when($filters['classification'], fn (Builder $inner, string $value): Builder => $inner->where('eligible_po_line.procurement_classification', $value));
                });
            })->select(['bill.id', 'bill.total_minor'])->distinct();
    }

    /** @param ProcurementFilters $filters */
    private function apGeneralLedger(int $companyId, array $filters): ?int
    {
        if ($filters['cost_center_id'] !== null || $filters['vendor_id'] !== null || $filters['classification'] !== null) {
            return null;
        }

        return $this->integer($this->db->table('payroll_accounting_journal_lines as line')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')
            ->where('journal.company_id', $companyId)->whereIn('journal.status', ['internally_posted', 'exported'])
            ->whereIn('journal.source_type', ['vendor_bill', 'ap_payment'])->where('line.account_code_snapshot', '2100')
            ->whereBetween('journal.posting_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['branch_id'], fn (Builder $query, int $id): Builder => $query->where('journal.branch_id', $id))
            ->sum($this->db->raw('CAST(line.credit_minor AS SIGNED)-CAST(line.debit_minor AS SIGNED)')));
    }

    /** @param ProcurementFilters $filters */
    private function scope(int $companyId, array $filters): Builder
    {
        $query = $this->db->table('purchase_order_lines as l')->join('purchase_orders as po', 'po.id', '=', 'l.purchase_order_id');
        $this->apply($query, $companyId, $filters, 'po', 'l');

        return $query;
    }

    /** @param ProcurementFilters $filters */
    private function apply(Builder $query, int $companyId, array $filters, string $header, string $line): void
    {
        $query->where($header.'.company_id', $companyId)->whereBetween($header.'.order_date', [$filters['date_from'], $filters['date_to']])->where($header.'.currency', $filters['currency'])
            ->when($filters['branch_id'], fn (Builder $q, int $id): Builder => $q->where($header.'.branch_id', $id))
            ->when($filters['vendor_id'], fn (Builder $q, int $id): Builder => $q->where($header.'.vendor_id', $id))
            ->when($filters['cost_center_id'], fn (Builder $q, int $id): Builder => $q->where($line.'.cost_center_id', $id))
            ->when($filters['classification'], fn (Builder $q, string $value): Builder => $q->where($line.'.procurement_classification', $value));
    }

    private function field(?object $row, string $key): int
    {
        if ($row === null) {
            return 0;
        }

        return $this->integer($row->{$key} ?? null);
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
