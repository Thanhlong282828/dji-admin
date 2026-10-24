<?php

namespace Modules\Commerce\Application\Queries;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Modules\Company\Domain\Services\BranchAccessService;

final readonly class GetUnifiedSalesHistoryHandler
{
    public function __construct(private ConnectionInterface $db, private BranchAccessService $access) {}

    /** @return array<string,mixed> */
    public function handle(GetUnifiedSalesHistoryQuery $request): array
    {
        abort_unless($this->access->company($request->actor, $request->companyId), 404);
        $branches = $this->db->table('branches as b')->where('b.company_id', $request->companyId)->whereNull('b.deleted_at')
            ->when(! $request->actor->can('organization.tenant-all'), fn (Builder $query): Builder => $query->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'b.id')->where('bua.user_id', $request->actor->id)))
            ->orderBy('b.name')->get(['b.id', 'b.name']);
        if ($request->branchId > 0) {
            abort_unless($branches->contains('id', $request->branchId), 404);
        }

        $orderLines = $this->db->table('order_items')->selectRaw("order_id, COUNT(*) item_count, SUM(quantity) quantity, GROUP_CONCAT(DISTINCT product_name_snapshot ORDER BY product_name_snapshot SEPARATOR ', ') product_names")->groupBy('order_id');
        $marketplace = $this->db->table('orders as o')->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')->join('stores as s', 's.id', '=', 'o.store_id')->joinSub($orderLines, 'lines', 'lines.order_id', '=', 'o.id')
            ->leftJoin('commerce_payment_records as pay', static fn (JoinClause $join): JoinClause => $join->on('pay.order_id', '=', 'o.id')->where('pay.status', 'succeeded'))
            ->leftJoin('commerce_fulfillments as f', 'f.order_id', '=', 'o.id')->leftJoin('branches as b', 'b.id', '=', 'f.branch_id')
            ->leftJoin('branch_management_revenues as rev', static fn (JoinClause $join): JoinClause => $join->on('rev.order_id', '=', 'o.id')->where('rev.source_type', 'commerce_order')->where('rev.status', 'recognized'))
            ->leftJoin('commerce_sale_reversals as reversal', static fn (JoinClause $join): JoinClause => $join->on('reversal.source_id', '=', 'o.id')->where('reversal.source_type', 'marketplace')->where('reversal.status', 'completed'))
            ->where('sp.company_id', $request->companyId)->where('o.payment_status', 'paid')
            ->selectRaw("'marketplace' source, o.id record_id, o.order_number reference, o.paid_at occurred_at, o.status, o.payment_status, sp.display_name seller_name, s.name store_name, b.id branch_id, b.name branch_name, NULL customer_name, lines.item_count, lines.quantity, lines.product_names, NULL service_names, o.total_minor, rev.amount_minor recognized_revenue_minor, rev.reference revenue_reference, pay.method payment_method, pay.settlement_reference, f.status fulfillment_status, reversal.id reversal_id, reversal.reference reversal_reference, reversal.amount_minor reversal_amount_minor");

        $posProducts = $this->db->table('customer_visit_products')->selectRaw("customer_visit_id, COUNT(*) item_count, SUM(quantity) quantity, GROUP_CONCAT(DISTINCT product_name_snapshot ORDER BY product_name_snapshot SEPARATOR ', ') product_names")->groupBy('customer_visit_id');
        $posServices = $this->db->table('customer_visit_services')->selectRaw("customer_visit_id, SUM(quantity) quantity, GROUP_CONCAT(DISTINCT service_name_snapshot ORDER BY service_name_snapshot SEPARATOR ', ') service_names")->groupBy('customer_visit_id');
        $pos = $this->db->table('branch_pos_receipts as r')->join('customer_visits as v', 'v.id', '=', 'r.customer_visit_id')->join('commerce_customers as c', 'c.id', '=', 'v.customer_id')->join('branches as b', 'b.id', '=', 'r.branch_id')
            ->leftJoinSub($posProducts, 'products', 'products.customer_visit_id', '=', 'v.id')->leftJoinSub($posServices, 'services', 'services.customer_visit_id', '=', 'v.id')->join('branch_management_revenues as rev', 'rev.id', '=', 'r.revenue_id')->leftJoin('commerce_sale_reversals as reversal', static fn (JoinClause $join): JoinClause => $join->on('reversal.source_id', '=', 'r.id')->where('reversal.source_type', 'pos')->where('reversal.status', 'completed'))
            ->where('r.company_id', $request->companyId)->where('r.status', 'paid')
            ->selectRaw("'pos' source, r.id record_id, r.reference, r.paid_at occurred_at, r.status, 'paid' payment_status, NULL seller_name, NULL store_name, b.id branch_id, b.name branch_name, c.full_name customer_name, COALESCE(products.item_count,0) item_count, COALESCE(products.quantity,0)+COALESCE(services.quantity,0) quantity, products.product_names, services.service_names, r.total_minor, rev.amount_minor recognized_revenue_minor, rev.reference revenue_reference, r.payment_method, NULL settlement_reference, 'completed' fulfillment_status, reversal.id reversal_id, reversal.reference reversal_reference, reversal.amount_minor reversal_amount_minor");

        $sales = $this->db->table('orders')->fromSub($marketplace->unionAll($pos), 'sales');
        $this->filters($sales, $request);
        $summary = (clone $sales)->selectRaw("COUNT(*) transactions, SUM(source='marketplace') marketplace_transactions, SUM(source='pos') pos_transactions, SUM(reversal_id IS NOT NULL) reversed_transactions, COALESCE(SUM(recognized_revenue_minor),0)-COALESCE(SUM(reversal_amount_minor),0) recognized_revenue_minor")->first();
        $items = $sales->orderByDesc('occurred_at')->orderByDesc('record_id')->paginate(30)->withQueryString();

        return compact('items', 'summary', 'branches') + ['companyId' => $request->companyId];
    }

    private function filters(Builder $query, GetUnifiedSalesHistoryQuery $request): void
    {
        $query->when(in_array($request->source, ['marketplace', 'pos'], true), fn (Builder $builder): Builder => $builder->where('source', $request->source))
            ->when($request->search !== '', fn (Builder $builder): Builder => $builder->where('reference', 'like', '%'.$request->search.'%'))
            ->when($request->dateFrom !== '', fn (Builder $builder): Builder => $builder->whereDate('occurred_at', '>=', $request->dateFrom))
            ->when($request->dateTo !== '', fn (Builder $builder): Builder => $builder->whereDate('occurred_at', '<=', $request->dateTo))
            ->when($request->branchId > 0, fn (Builder $builder): Builder => $builder->where('branch_id', $request->branchId));
    }
}
