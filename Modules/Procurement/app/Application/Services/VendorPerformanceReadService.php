<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/** @phpstan-type VendorFilters array{date_from:string,date_to:string,branch_id:?int,cost_center_id:?int,vendor_id:?int,classification:?string,currency:string,sort:string,direction:string} */
final readonly class VendorPerformanceReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  VendorFilters  $filters
     * @param  list<array<string,mixed>>  $exceptionRows
     * @return array{rows:list<array<string,mixed>>,evidence:array<string,string>,filters:VendorFilters}
     */
    public function read(int $companyId, array $filters, array $exceptionRows): array
    {
        $vendors = $this->db->table('procurement_vendors')->where('company_id', $companyId)->when($filters['vendor_id'], fn (Builder $query, int $id): Builder => $query->where('id', $id))->orderBy('name')->get(['id', 'code', 'name', 'status'])->keyBy('id');
        $accepted = $this->db->table('service_acceptance_lines as line')->join('service_acceptances as acceptance', 'acceptance.id', '=', 'line.service_acceptance_id')->where('acceptance.status', 'approved')->selectRaw('line.purchase_order_line_id, SUM(line.accepted_minor) amount')->groupBy('line.purchase_order_line_id');

        $orders = $this->db->table('purchase_orders as po')->join('purchase_order_lines as line', 'line.purchase_order_id', '=', 'po.id')->leftJoinSub($accepted, 'accepted', 'accepted.purchase_order_line_id', '=', 'line.id')->whereNotIn('po.status', ['draft', 'cancelled']);
        $this->scope($orders, $companyId, $filters, 'po', 'line', 'order_date');
        $orderMetrics = $orders->selectRaw("po.vendor_id,
            COUNT(DISTINCT po.id) po_count,
            SUM(line.line_total_minor) ordered,
            SUM(CASE WHEN line.procurement_classification='inventory' THEN line.received_quantity*line.unit_cost_minor ELSE 0 END) goods_received,
            SUM(CASE WHEN line.procurement_classification='operating_expense' THEN COALESCE(accepted.amount,0) ELSE 0 END) service_accepted,
            SUM(GREATEST(line.line_total_minor-CASE WHEN line.procurement_classification='operating_expense' THEN COALESCE(accepted.amount,0) ELSE line.received_quantity*line.unit_cost_minor END,0)) open_commitment,
            COUNT(DISTINCT CASE WHEN line.procurement_classification='inventory' AND line.received_quantity<line.ordered_quantity THEN po.id END) awaiting_receipt,
            COUNT(DISTINCT CASE WHEN line.procurement_classification='operating_expense' AND COALESCE(accepted.amount,0)<line.line_total_minor THEN po.id END) awaiting_acceptance")
            ->groupBy('po.vendor_id')->get()->keyBy('vendor_id');

        $billLines = $this->db->table('vendor_bill_lines as line')->join('vendor_bills as bill', 'bill.id', '=', 'line.vendor_bill_id')->leftJoin('purchase_order_lines as po_line', 'po_line.id', '=', 'line.purchase_order_line_id')->whereNotIn('bill.status', ['draft', 'cancelled', 'rejected']);
        $this->scope($billLines, $companyId, $filters, 'bill', 'po_line', 'invoice_date');
        $billing = $billLines->selectRaw("bill.vendor_id,
            SUM(line.line_total_minor) billed,
            SUM(CASE WHEN po_line.procurement_classification='inventory' THEN line.line_total_minor ELSE 0 END) goods_billed,
            SUM(CASE WHEN po_line.procurement_classification='operating_expense' THEN line.line_total_minor ELSE 0 END) service_billed")
            ->groupBy('bill.vendor_id')->get()->keyBy('vendor_id');

        $allocated = $this->db->table('ap_payment_allocations')->selectRaw('vendor_bill_id, SUM(allocated_minor) amount')->groupBy('vendor_bill_id');
        $payables = $this->db->table('vendor_bills as bill')->leftJoinSub($allocated, 'allocated', 'allocated.vendor_bill_id', '=', 'bill.id')->whereNotIn('bill.status', ['draft', 'cancelled', 'rejected']);
        $this->headerScope($payables, $companyId, $filters, 'bill', 'invoice_date');
        $this->linkedLineScope($payables, $filters, 'bill.id');
        $payableMetrics = $payables->selectRaw('bill.vendor_id, SUM(COALESCE(allocated.amount,0)) paid,
            SUM(bill.total_minor-COALESCE(allocated.amount,0)) outstanding,
            SUM(CASE WHEN bill.due_date<? THEN bill.total_minor-COALESCE(allocated.amount,0) ELSE 0 END) overdue', [$filters['date_to']])
            ->groupBy('bill.vendor_id')->get()->keyBy('vendor_id');

        $fulfillment = $this->fulfillment($companyId, $filters);
        $exceptionCounts = collect($exceptionRows)->filter(static fn (array $row): bool => is_numeric($row['vendor_id'] ?? null))->countBy('vendor_id');
        $rows = [];
        foreach ($vendors as $vendor) {
            $id = $this->integer($vendor->id ?? null);
            $order = $orderMetrics->get($id);
            $bill = $billing->get($id);
            $ap = $payableMetrics->get($id);
            $delivery = $fulfillment[$id] ?? ['observations' => 0, 'on_time' => 0, 'late' => 0, 'delay_days' => null];
            $goodsReceived = $this->field($order, 'goods_received');
            $serviceAccepted = $this->field($order, 'service_accepted');
            $goodsBilled = $this->field($bill, 'goods_billed');
            $serviceBilled = $this->field($bill, 'service_billed');
            $rows[] = ['vendor_id' => $id, 'code' => $this->text($vendor->code ?? null), 'name' => $this->text($vendor->name ?? null), 'status' => $this->text($vendor->status ?? null), 'po_count' => $this->field($order, 'po_count'), 'ordered' => $this->field($order, 'ordered'), 'goods_received' => $goodsReceived, 'service_accepted' => $serviceAccepted, 'billed' => $this->field($bill, 'billed'), 'paid' => $this->field($ap, 'paid'), 'outstanding' => $this->field($ap, 'outstanding'), 'overdue' => $this->field($ap, 'overdue'), 'open_commitment' => $this->field($order, 'open_commitment'), 'received_not_billed' => max(0, $goodsReceived - $goodsBilled), 'accepted_not_billed' => max(0, $serviceAccepted - $serviceBilled), 'awaiting_receipt' => $this->field($order, 'awaiting_receipt'), 'awaiting_acceptance' => $this->field($order, 'awaiting_acceptance'), 'exception_count' => $this->integer($exceptionCounts->get($id)), 'fulfillment_observations' => $delivery['observations'], 'on_time' => $delivery['on_time'], 'late' => $delivery['late'], 'average_delay_days' => $delivery['delay_days']];
        }

        $sort = $filters['sort'];
        usort($rows, static function (array $left, array $right) use ($sort, $filters): int {
            $comparison = ($left[$sort] ?? 0) <=> ($right[$sort] ?? 0);

            return $filters['direction'] === 'asc' ? $comparison : -$comparison;
        });

        return ['rows' => $rows, 'filters' => $filters, 'evidence' => ['quality' => 'NOT_ENOUGH_EVIDENCE', 'sla_score' => 'NOT_ENOUGH_EVIDENCE', 'rating' => 'NOT_ENOUGH_EVIDENCE', 'invoice_delay' => 'NOT_ENOUGH_EVIDENCE', 'fulfillment' => array_sum(array_column($rows, 'fulfillment_observations')) > 0 ? 'SUPPORTED_FOR_DATED_OBSERVATIONS_ONLY' : 'NOT_ENOUGH_EVIDENCE', 'documents' => 'NOT_ENOUGH_EVIDENCE']];
    }

    /** @param VendorFilters $filters
     * @return array<int,array{observations:int,on_time:int,late:int,delay_days:?int}>
     */
    private function fulfillment(int $companyId, array $filters): array
    {
        $events = $this->db->table('purchase_orders as po')->join('procurement_goods_receipts as event', 'event.purchase_order_id', '=', 'po.id')->where('event.status', 'received')->whereNotNull('po.expected_date');
        $this->headerScope($events, $companyId, $filters, 'po', 'order_date');
        $events->when($filters['classification'] !== null && $filters['classification'] !== 'inventory', fn (Builder $query): Builder => $query->whereRaw('1=0'))
            ->when($filters['cost_center_id'], fn (Builder $query, int $id): Builder => $query->whereExists(fn (Builder $line): Builder => $line->selectRaw('1')->from('procurement_goods_receipt_lines as grl')->join('purchase_order_lines as pol', 'pol.id', '=', 'grl.purchase_order_line_id')->whereColumn('grl.goods_receipt_id', 'event.id')->where('pol.cost_center_id', $id)));
        $goods = $events->selectRaw('po.vendor_id, COUNT(DISTINCT event.id) observations, SUM(event.receipt_date<=po.expected_date) on_time, SUM(event.receipt_date>po.expected_date) late, SUM(GREATEST(DATEDIFF(event.receipt_date,po.expected_date),0)) delay_total')->groupBy('po.vendor_id')->get()->keyBy('vendor_id');
        $services = $this->db->table('purchase_orders as po')->join('service_acceptances as event', 'event.purchase_order_id', '=', 'po.id')->where('event.status', 'approved')->whereNotNull('po.expected_date');
        $this->headerScope($services, $companyId, $filters, 'po', 'order_date');
        $services->when($filters['classification'] !== null && $filters['classification'] !== 'operating_expense', fn (Builder $query): Builder => $query->whereRaw('1=0'))
            ->when($filters['cost_center_id'], fn (Builder $query, int $id): Builder => $query->whereExists(fn (Builder $line): Builder => $line->selectRaw('1')->from('service_acceptance_lines as sal')->whereColumn('sal.service_acceptance_id', 'event.id')->where('sal.cost_center_id', $id)));
        $service = $services->selectRaw('po.vendor_id, COUNT(DISTINCT event.id) observations, SUM(event.acceptance_date<=po.expected_date) on_time, SUM(event.acceptance_date>po.expected_date) late, SUM(GREATEST(DATEDIFF(event.acceptance_date,po.expected_date),0)) delay_total')->groupBy('po.vendor_id')->get()->keyBy('vendor_id');
        $result = [];
        foreach ($goods->keys()->merge($service->keys())->unique() as $vendorId) {
            $id = $this->integer($vendorId);
            $goodsRow = $goods->get($id);
            $serviceRow = $service->get($id);
            $observations = $this->field($goodsRow, 'observations') + $this->field($serviceRow, 'observations');
            $delay = $this->field($goodsRow, 'delay_total') + $this->field($serviceRow, 'delay_total');
            $result[$id] = ['observations' => $observations, 'on_time' => $this->field($goodsRow, 'on_time') + $this->field($serviceRow, 'on_time'), 'late' => $this->field($goodsRow, 'late') + $this->field($serviceRow, 'late'), 'delay_days' => $observations > 0 ? intdiv($delay, $observations) : null];
        }

        return $result;
    }

    /** @param VendorFilters $filters */
    private function scope(Builder $query, int $companyId, array $filters, string $header, string $line, string $date): void
    {
        $this->headerScope($query, $companyId, $filters, $header, $date);
        $query->when($filters['cost_center_id'], fn (Builder $inner, int $id): Builder => $inner->where($line.'.cost_center_id', $id))->when($filters['classification'], fn (Builder $inner, string $value): Builder => $inner->where($line.'.procurement_classification', $value));
    }

    /** @param VendorFilters $filters */
    private function headerScope(Builder $query, int $companyId, array $filters, string $header, string $date): void
    {
        $query->where($header.'.company_id', $companyId)->whereBetween($header.'.'.$date, [$filters['date_from'], $filters['date_to']])->where($header.'.currency', $filters['currency'])->when($filters['branch_id'], fn (Builder $inner, int $id): Builder => $inner->where($header.'.branch_id', $id))->when($filters['vendor_id'], fn (Builder $inner, int $id): Builder => $inner->where($header.'.vendor_id', $id));
    }

    /** @param VendorFilters $filters */
    private function linkedLineScope(Builder $query, array $filters, string $billId): void
    {
        if ($filters['cost_center_id'] === null && $filters['classification'] === null) {
            return;
        }

        $query->whereExists(function (Builder $lines) use ($filters, $billId): void {
            $lines->selectRaw('1')->from('vendor_bill_lines as linked_bill_line')->join('purchase_order_lines as linked_po_line', 'linked_po_line.id', '=', 'linked_bill_line.purchase_order_line_id')->whereColumn('linked_bill_line.vendor_bill_id', $billId)
                ->when($filters['cost_center_id'], fn (Builder $inner, int $id): Builder => $inner->where('linked_po_line.cost_center_id', $id))
                ->when($filters['classification'], fn (Builder $inner, string $value): Builder => $inner->where('linked_po_line.procurement_classification', $value));
        });
    }

    private function field(?object $row, string $field): int
    {
        return $row === null ? 0 : $this->integer($row->{$field} ?? null);
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
