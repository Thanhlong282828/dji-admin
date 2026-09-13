<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/** @phpstan-type ExceptionFilters array{date_from:string,date_to:string,branch_id:?int,cost_center_id:?int,vendor_id:?int,classification:?string,currency:string,exception_type:?string,severity:?string,business_status:?string} */
final readonly class ProcurementExceptionReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  ExceptionFilters  $filters
     * @param  array<string,mixed>  $reconciliation
     * @return array{rows:list<array<string,mixed>>,counts:array<string,int>,total:int}
     */
    public function read(int $companyId, array $filters, array $reconciliation): array
    {
        $rows = $this->rows();
        $this->purchaseOrderExceptions($rows, $companyId, $filters);
        $this->goodsBillingExceptions($rows, $companyId, $filters);
        $this->serviceBillingExceptions($rows, $companyId, $filters);
        $this->payableExceptions($rows, $companyId, $filters);
        $this->serviceControlExceptions($rows, $companyId, $filters);
        $this->financialExceptions($rows, $companyId, $reconciliation);

        $filtered = $rows->filter(function (array $row) use ($filters): bool {
            return ($filters['exception_type'] === null || $row['exception_type'] === $filters['exception_type'])
                && ($filters['severity'] === null || $row['severity'] === $filters['severity'])
                && ($filters['business_status'] === null || $row['business_status'] === $filters['business_status']);
        })->values();

        $counts = [];
        foreach ($filtered as $row) {
            $type = $this->text($row['exception_type']);
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return ['rows' => array_values($filtered->all()), 'counts' => $counts, 'total' => $filtered->count()];
    }

    /** @param Collection<int,array<string,mixed>> $rows
     * @param  ExceptionFilters  $filters
     */
    private function purchaseOrderExceptions(Collection $rows, int $companyId, array $filters): void
    {
        $accepted = $this->db->table('service_acceptance_lines as sal')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')->where('sa.status', 'approved')->selectRaw('sal.purchase_order_line_id, SUM(sal.accepted_minor) amount')->groupBy('sal.purchase_order_line_id');
        $query = $this->db->table('purchase_orders as po')->join('purchase_order_lines as line', 'line.purchase_order_id', '=', 'po.id')->join('procurement_vendors as vendor', 'vendor.id', '=', 'po.vendor_id')->join('branches as branch', 'branch.id', '=', 'po.branch_id')->leftJoin('cost_centers as center', 'center.id', '=', 'line.cost_center_id')->leftJoinSub($accepted, 'accepted', 'accepted.purchase_order_line_id', '=', 'line.id')->whereIn('po.status', ['approved', 'sent', 'partially_received']);
        $this->scope($query, $companyId, $filters, 'po', 'line');
        foreach ($query->selectRaw('po.id, po.reference, po.status, po.expected_date, vendor.id vendor_id, vendor.name vendor_name, branch.name branch_name, center.code cost_center_code, line.procurement_classification, SUM(line.line_total_minor) ordered, SUM(line.received_quantity*line.unit_cost_minor) received, SUM(COALESCE(accepted.amount,0)) accepted, GREATEST(DATEDIFF(CURRENT_DATE,po.expected_date),0) age_days')->groupBy('po.id', 'po.reference', 'po.status', 'po.expected_date', 'vendor.id', 'vendor.name', 'branch.name', 'center.code', 'line.procurement_classification')->get() as $source) {
            $isService = $source->procurement_classification === 'operating_expense';
            $realized = $this->integer($isService ? $source->accepted : $source->received);
            $ordered = $this->integer($source->ordered);
            if ($realized >= $ordered) {
                continue;
            }
            $this->push($rows, $isService ? 'PO_AWAITING_SERVICE_ACCEPTANCE' : 'PO_AWAITING_GOODS_RECEIPT', $companyId, 'purchase_order', $source, $ordered - $realized, $this->integer($source->age_days ?? null) > 0 ? 'WARNING' : 'INFO', $isService ? 'admin.procurement.service-acceptances' : 'admin.procurement.receipts', 'purchase-order.view', $isService ? 'service-acceptance.view' : 'goods-receipt.view');
        }
    }

    /** @param Collection<int,array<string,mixed>> $rows
     * @param  ExceptionFilters  $filters
     */
    private function goodsBillingExceptions(Collection $rows, int $companyId, array $filters): void
    {
        $billed = $this->db->table('vendor_bill_lines as bl')->join('vendor_bills as bill', 'bill.id', '=', 'bl.vendor_bill_id')->whereNotIn('bill.status', ['draft', 'cancelled', 'rejected'])->whereNotExists(fn (Builder $q): Builder => $q->selectRaw('1')->from('vendor_bill_reversal_requests as reversal')->whereColumn('reversal.vendor_bill_id', 'bill.id')->where('reversal.status', 'reversed')->where('reversal.effective_at', '<=', $filters['date_to'].' 23:59:59'))->selectRaw('bl.purchase_order_line_id, SUM(bl.line_total_minor) amount')->groupBy('bl.purchase_order_line_id');
        $query = $this->db->table('purchase_order_lines as line')->join('purchase_orders as po', 'po.id', '=', 'line.purchase_order_id')->join('procurement_vendors as vendor', 'vendor.id', '=', 'po.vendor_id')->join('branches as branch', 'branch.id', '=', 'po.branch_id')->leftJoin('cost_centers as center', 'center.id', '=', 'line.cost_center_id')->leftJoinSub($billed, 'billed', 'billed.purchase_order_line_id', '=', 'line.id')->where('line.procurement_classification', 'inventory')->where('line.received_quantity', '>', 0);
        $this->scope($query, $companyId, $filters, 'po', 'line');
        foreach ($query->get(['po.id', 'po.reference', 'po.status', 'vendor.id as vendor_id', 'vendor.name as vendor_name', 'branch.name as branch_name', 'center.code as cost_center_code', 'line.received_quantity', 'line.unit_cost_minor', 'billed.amount as billed_minor']) as $source) {
            $amount = $this->integer($source->received_quantity) * $this->integer($source->unit_cost_minor) - $this->integer($source->billed_minor);
            if ($amount > 0) {
                $this->push($rows, 'GOODS_RECEIVED_NOT_BILLED', $companyId, 'purchase_order', $source, $amount, 'WARNING', 'admin.procurement.payables', 'goods-receipt.view', 'vendor-bill.view');
            }
        }
    }

    /** @param Collection<int,array<string,mixed>> $rows
     * @param  ExceptionFilters  $filters
     */
    private function serviceBillingExceptions(Collection $rows, int $companyId, array $filters): void
    {
        $billed = $this->db->table('vendor_bill_lines as bl')->join('vendor_bills as bill', 'bill.id', '=', 'bl.vendor_bill_id')->whereNotIn('bill.status', ['draft', 'cancelled', 'rejected'])->whereNotExists(fn (Builder $q): Builder => $q->selectRaw('1')->from('vendor_bill_reversal_requests as reversal')->whereColumn('reversal.vendor_bill_id', 'bill.id')->where('reversal.status', 'reversed')->where('reversal.effective_at', '<=', $filters['date_to'].' 23:59:59'))->selectRaw('bl.service_acceptance_line_id, SUM(bl.line_total_minor) amount')->groupBy('bl.service_acceptance_line_id');
        $query = $this->db->table('service_acceptance_lines as line')->join('service_acceptances as sa', 'sa.id', '=', 'line.service_acceptance_id')->join('procurement_vendors as vendor', 'vendor.id', '=', 'sa.vendor_id')->join('branches as branch', 'branch.id', '=', 'sa.branch_id')->leftJoin('cost_centers as center', 'center.id', '=', 'line.cost_center_id')->leftJoinSub($billed, 'billed', 'billed.service_acceptance_line_id', '=', 'line.id')->where('sa.status', 'approved');
        $this->scope($query, $companyId, $filters, 'sa', 'line', 'acceptance_date');
        foreach ($query->get(['sa.id', 'sa.reference', 'sa.status', 'vendor.id as vendor_id', 'vendor.name as vendor_name', 'branch.name as branch_name', 'center.code as cost_center_code', 'line.accepted_minor', 'billed.amount as billed_minor']) as $source) {
            $amount = $this->integer($source->accepted_minor) - $this->integer($source->billed_minor);
            if ($amount > 0) {
                $this->push($rows, 'SERVICE_ACCEPTED_NOT_BILLED', $companyId, 'service_acceptance', $source, $amount, 'WARNING', 'admin.procurement.payables', 'service-acceptance.view', 'vendor-bill.view');
            }
        }
    }

    /** @param Collection<int,array<string,mixed>> $rows
     * @param  ExceptionFilters  $filters
     */
    private function payableExceptions(Collection $rows, int $companyId, array $filters): void
    {
        $allocated = $this->db->table('ap_payment_allocations as allocation')->leftJoin('ap_payment_allocation_reversals as reversal', fn (JoinClause $join): JoinClause => $join->on('reversal.ap_payment_allocation_id', '=', 'allocation.id')->where('reversal.effective_at', '<=', $filters['date_to'].' 23:59:59'))->selectRaw('allocation.vendor_bill_id, SUM(allocation.allocated_minor-COALESCE(reversal.reversed_minor,0)) amount')->groupBy('allocation.vendor_bill_id');
        $query = $this->db->table('vendor_bills as bill')->join('procurement_vendors as vendor', 'vendor.id', '=', 'bill.vendor_id')->join('branches as branch', 'branch.id', '=', 'bill.branch_id')->leftJoinSub($allocated, 'allocated', 'allocated.vendor_bill_id', '=', 'bill.id')->whereNotIn('bill.status', ['draft', 'cancelled', 'rejected'])->whereNotExists(fn (Builder $q): Builder => $q->selectRaw('1')->from('vendor_bill_reversal_requests as reversal')->whereColumn('reversal.vendor_bill_id', 'bill.id')->where('reversal.status', 'reversed')->where('reversal.effective_at', '<=', $filters['date_to'].' 23:59:59'));
        $this->headerScope($query, $companyId, $filters, 'bill', 'invoice_date');
        $this->linkedLineScope($query, $filters, 'bill.id', 'vendor_bill_lines', 'vendor_bill_id', 'purchase_order_line_id');
        foreach ($query->selectRaw('bill.id, bill.invoice_number reference, bill.status, bill.due_date, bill.total_minor, COALESCE(allocated.amount,0) paid_minor, vendor.id vendor_id, vendor.name vendor_name, branch.name branch_name, GREATEST(DATEDIFF(CURRENT_DATE,bill.due_date),0) age_days')->get() as $source) {
            $amount = $this->integer($source->total_minor) - $this->integer($source->paid_minor);
            if ($amount <= 0) {
                continue;
            }
            $ageDays = $this->integer($source->age_days ?? null);
            $this->push($rows, $ageDays > 0 ? 'VENDOR_BILL_OVERDUE' : 'VENDOR_BILL_UNPAID', $companyId, 'vendor_bill', $source, $amount, $ageDays > 0 ? 'WARNING' : 'INFO', 'admin.procurement.payables', 'vendor-bill.view', 'vendor-bill.view');
        }
    }

    /** @param Collection<int,array<string,mixed>> $rows
     * @param  ExceptionFilters  $filters
     */
    private function serviceControlExceptions(Collection $rows, int $companyId, array $filters): void
    {
        $query = $this->db->table('service_acceptances as sa')->join('procurement_vendors as vendor', 'vendor.id', '=', 'sa.vendor_id')->join('branches as branch', 'branch.id', '=', 'sa.branch_id')->where('sa.status', 'reversal_requested');
        $this->headerScope($query, $companyId, $filters, 'sa', 'acceptance_date');
        $this->linkedLineScope($query, $filters, 'sa.id', 'service_acceptance_lines', 'service_acceptance_id', 'purchase_order_line_id');
        foreach ($query->get(['sa.id', 'sa.reference', 'sa.status', 'sa.total_minor', 'vendor.id as vendor_id', 'vendor.name as vendor_name', 'branch.name as branch_name']) as $source) {
            $this->push($rows, 'SERVICE_REVERSAL_PENDING', $companyId, 'service_acceptance', $source, $this->integer($source->total_minor), 'WARNING', 'admin.procurement.service-acceptances', 'service-acceptance.view', 'service-acceptance.reverse');
        }

        $workflow = $this->db->table('workflow_instances as instance')->join('workflow_tasks as task', 'task.workflow_instance_id', '=', 'instance.id')->join('service_acceptances as sa', 'sa.id', '=', 'instance.subject_id')->join('procurement_vendors as vendor', 'vendor.id', '=', 'sa.vendor_id')->join('branches as branch', 'branch.id', '=', 'sa.branch_id')->where('instance.company_id', $companyId)->whereIn('instance.subject_type', ['SERVICE_ACCEPTANCE_APPROVAL', 'SERVICE_ACCEPTANCE_REVERSAL_APPROVAL'])->whereIn('task.status', ['pending', 'claimed'])->whereIn('instance.engine_status', ['draft', 'running']);
        $this->headerScope($workflow, $companyId, $filters, 'sa', 'acceptance_date');
        $this->linkedLineScope($workflow, $filters, 'sa.id', 'service_acceptance_lines', 'service_acceptance_id', 'purchase_order_line_id');
        foreach ($workflow->get(['instance.id', 'sa.reference', 'sa.status', 'sa.total_minor', 'task.assignment_source as workflow_status', 'vendor.id as vendor_id', 'vendor.name as vendor_name', 'branch.name as branch_name']) as $source) {
            $this->push($rows, 'WORKFLOW_APPROVAL_PENDING', $companyId, 'workflow_instance', $source, $this->integer($source->total_minor), 'INFO', 'admin.approvals.index', 'approval.inbox.view', 'approval.inbox.view');
        }
    }

    /** @param Collection<int,array<string,mixed>> $rows
     * @param  array<string,mixed>  $reconciliation
     */
    private function financialExceptions(Collection $rows, int $companyId, array $reconciliation): void
    {
        foreach ([['GOODS_GRNI_RECONCILIATION_MISMATCH', 'goods', 'unbilled_received', 'grni'], ['SERVICE_ACCRUAL_RECONCILIATION_MISMATCH', 'service', 'unbilled_accepted', 'accrual'], ['AP_RECONCILIATION_MISMATCH', 'ap', 'outstanding', 'gl']] as [$type, $section, $expected, $actual]) {
            $expectedAmount = $this->sectionAmount($reconciliation, $section, $expected);
            $actualAmount = $this->sectionAmount($reconciliation, $section, $actual);
            if ($expectedAmount === null || $actualAmount === null || $expectedAmount === $actualAmount) {
                continue;
            }
            $rows->push($this->row($type, 'CRITICAL', $companyId, 'company', $companyId, 'Company #'.$companyId, null, null, null, abs($expectedAmount - $actualAmount), 'VND', 'mismatch', null, null, 'admin.procurement.reports', 'procurement.view', 'procurement.view'));
        }

        $budget = $reconciliation['budget'] ?? null;
        if (! is_array($budget)) {
            return;
        }
        $status = $this->text($budget['status'] ?? null);
        $approved = $this->integer($budget['approved'] ?? 0);
        $consumed = $this->integer($budget['consumed'] ?? 0);
        $type = match ($status) {
            'NO_BUDGET' => 'BUDGET_NO_BUDGET',
            'PARTIAL_EVIDENCE' => 'BUDGET_PARTIAL_EVIDENCE',
            'OVER_BUDGET' => $consumed > $approved ? 'BUDGET_OVER_CONSUMED' : null,
            default => null,
        };
        if ($type === 'BUDGET_NO_BUDGET' && $consumed === 0) {
            return;
        }
        if ($type !== null) {
            $rows->push($this->row($type, $type === 'BUDGET_OVER_CONSUMED' ? 'WARNING' : 'INFO', $companyId, 'company', $companyId, 'Company #'.$companyId, null, null, null, $consumed, 'VND', $status, null, null, 'admin.finance.cost-center-costs', 'finance.operations.view', 'finance.operations.view'));
        }
    }

    /** @param ExceptionFilters $filters */
    private function scope(Builder $query, int $companyId, array $filters, string $header, string $line, string $date = 'order_date'): void
    {
        $this->headerScope($query, $companyId, $filters, $header, $date);
        $query->when($filters['cost_center_id'], fn (Builder $inner, int $id): Builder => $inner->where($line.'.cost_center_id', $id))->when($filters['classification'], fn (Builder $inner, string $value): Builder => $inner->where($line.'.procurement_classification', $value));
    }

    /** @param ExceptionFilters $filters */
    private function headerScope(Builder $query, int $companyId, array $filters, string $header, string $date): void
    {
        $query->where($header.'.company_id', $companyId)->whereBetween($header.'.'.$date, [$filters['date_from'], $filters['date_to']])->where($header.'.currency', $filters['currency'])->when($filters['branch_id'], fn (Builder $inner, int $id): Builder => $inner->where($header.'.branch_id', $id))->when($filters['vendor_id'], fn (Builder $inner, int $id): Builder => $inner->where($header.'.vendor_id', $id));
    }

    /** @param ExceptionFilters $filters */
    private function linkedLineScope(Builder $query, array $filters, string $headerId, string $table, string $foreignKey, string $poLineKey): void
    {
        if ($filters['cost_center_id'] === null && $filters['classification'] === null) {
            return;
        }

        $query->whereExists(function (Builder $lines) use ($filters, $headerId, $table, $foreignKey, $poLineKey): void {
            $lines->selectRaw('1')->from($table.' as linked_line')->join('purchase_order_lines as linked_po_line', 'linked_po_line.id', '=', 'linked_line.'.$poLineKey)->whereColumn('linked_line.'.$foreignKey, $headerId)
                ->when($filters['cost_center_id'], fn (Builder $inner, int $id): Builder => $inner->where('linked_po_line.cost_center_id', $id))
                ->when($filters['classification'], fn (Builder $inner, string $value): Builder => $inner->where('linked_po_line.procurement_classification', $value));
        });
    }

    /** @param Collection<int,array<string,mixed>> $rows */
    private function push(Collection $rows, string $type, int $companyId, string $sourceType, object $source, int $amount, string $severity, string $route, string $viewPermission, string $actionPermission): void
    {
        $ageDays = isset($source->age_days) ? $this->integer($source->age_days) : null;
        $row = $this->row($type, $severity, $companyId, $sourceType, $this->integer($source->id ?? null), $this->text($source->reference ?? null), $source->vendor_name ?? null, $source->branch_name ?? null, $source->cost_center_code ?? null, $amount, 'VND', $this->text($source->status ?? null), $source->workflow_status ?? null, $ageDays, $route, $viewPermission, $actionPermission);
        $row['vendor_id'] = $this->integer($source->vendor_id ?? null);
        $rows->push($row);
    }

    /** @return array<string,mixed> */
    private function row(string $type, string $severity, int $companyId, string $sourceType, int $sourceId, string $reference, mixed $vendor, mixed $branch, mixed $costCenter, int $amount, string $currency, string $status, mixed $workflow, ?int $ageDays, string $route, string $viewPermission, string $actionPermission): array
    {
        return ['exception_type' => $type, 'severity' => $severity, 'company_id' => $companyId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'source_reference' => $reference, 'vendor_id' => null, 'vendor' => is_string($vendor) ? $vendor : null, 'branch' => is_string($branch) ? $branch : null, 'cost_center' => is_string($costCenter) ? $costCenter : null, 'amount_minor' => $amount, 'currency' => $currency, 'business_status' => $status, 'workflow_status' => is_string($workflow) ? $workflow : null, 'age_days' => $ageDays, 'destination_route' => $route, 'permission' => $viewPermission, 'action_permission' => $actionPermission];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** @return Collection<int,array<string,mixed>> */
    private function rows(): Collection
    {
        return new Collection;
    }

    /** @param array<string,mixed> $source */
    private function sectionAmount(array $source, string $section, string $field): ?int
    {
        $sectionValue = $source[$section] ?? null;
        if (! is_array($sectionValue) || ! is_numeric($sectionValue[$field] ?? null)) {
            return null;
        }

        return (int) $sectionValue[$field];
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
