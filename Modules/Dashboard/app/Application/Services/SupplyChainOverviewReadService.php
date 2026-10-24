<?php

namespace Modules\Dashboard\Application\Services;

use Illuminate\Database\ConnectionInterface;

final readonly class SupplyChainOverviewReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function read(int $companyId): array
    {
        $po = $this->db->table('purchase_orders as po')->join('purchase_order_lines as l', 'l.purchase_order_id', '=', 'po.id')->where('po.company_id', $companyId);
        $supply = (array) (clone $po)->selectRaw("COUNT(DISTINCT CASE WHEN po.status IN ('approved','sent','partially_received') THEN po.id END) open_purchase_orders, COALESCE(SUM(l.ordered_quantity),0) ordered_quantity, COALESCE(SUM(l.received_quantity),0) received_quantity, COALESCE(SUM(GREATEST(l.ordered_quantity-l.received_quantity,0)),0) inbound_quantity, COALESCE(SUM(GREATEST(l.ordered_quantity-l.received_quantity,0)*l.unit_cost_minor),0) inbound_value_minor")->first();
        $supply['pending_receipts'] = $this->db->table('procurement_goods_receipts')->where('company_id', $companyId)->where('status', 'draft')->count();
        $supply['vendor_outstanding_minor'] = $this->vendorOutstanding($companyId, false);
        $supply['overdue_vendor_bills_minor'] = $this->vendorOutstanding($companyId, true);

        $balances = $this->db->table('inventory_balances as b')->join('inventory_locations as l', 'l.id', '=', 'b.inventory_location_id')->join('stores as s', 's.id', '=', 'l.store_id')->join('seller_profiles as seller', 'seller.id', '=', 's.seller_profile_id')->where('seller.company_id', $companyId);
        $inventory = (array) (clone $balances)->selectRaw('COUNT(DISTINCT b.product_variant_id) skus, COALESCE(SUM(b.on_hand),0) on_hand, COALESCE(SUM(b.reserved),0) reserved, COALESCE(SUM(b.on_hand-b.reserved),0) available, COALESCE(SUM(b.inventory_value_minor),0) inventory_value_minor, SUM(CASE WHEN b.on_hand-b.reserved BETWEEN 1 AND 5 THEN 1 ELSE 0 END) low_stock, SUM(CASE WHEN b.on_hand-b.reserved<=0 THEN 1 ELSE 0 END) out_of_stock')->first();
        $inventory['pending_transfers'] = $this->db->table('inventory_stock_transfers')->where('company_id', $companyId)->whereIn('status', ['draft', 'in_transit'])->count();
        $inventory['in_transit'] = (int) $this->db->table('inventory_stock_transfers')->where('company_id', $companyId)->where('status', 'in_transit')->sum('quantity');

        $sales = $this->db->table('sales_orders as o')->join('sales_order_lines as l', 'l.sales_order_id', '=', 'o.id')->where('o.company_id', $companyId);
        $erp = (array) (clone $sales)->selectRaw("COUNT(DISTINCT CASE WHEN o.status IN ('confirmed','partially_fulfilled') THEN o.id END) open_orders, COALESCE(SUM(l.quantity),0) ordered_quantity, COALESCE(SUM(l.fulfilled_quantity),0) fulfilled_quantity, COALESCE(SUM(GREATEST(l.quantity-l.fulfilled_quantity,0)),0) remaining_quantity, COUNT(DISTINCT CASE WHEN o.status='partially_fulfilled' THEN o.id END) partial_orders, COUNT(DISTINCT CASE WHEN o.status='fulfilled' THEN o.id END) fulfilled_orders")->first();
        $marketplace = $this->db->table('orders as o')->join('seller_profiles as seller', 'seller.id', '=', 'o.seller_profile_id')->where('seller.company_id', $companyId);
        $demand = ['erp_sales' => $erp, 'marketplace_pending' => (clone $marketplace)->whereIn('o.status', ['confirmed', 'processing'])->count(), 'marketplace_ready' => $this->db->table('commerce_fulfillments')->where('company_id', $companyId)->whereIn('status', ['pending', 'packed'])->count()];

        $fulfillment = (array) $this->db->table('commerce_fulfillments')->where('company_id', $companyId)->selectRaw("SUM(CASE WHEN status IN ('pending','packed') THEN 1 ELSE 0 END) ready, SUM(CASE WHEN status='shipped' THEN 1 ELSE 0 END) in_transit, SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) delivered")->first();
        $fulfillment += ['failed' => 0, 'returned' => 0, 'external_carrier' => 'NOT_CONNECTED', 'delivery_performance' => 'NOT_ENOUGH_EVIDENCE'];

        $details = $this->db->table('sales_order_lines as l')->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')->join('commerce_customers as c', 'c.id', '=', 'o.customer_id')->leftJoin('product_variants as v', 'v.id', '=', 'l.product_variant_id')->where('o.company_id', $companyId)->whereIn('o.status', ['confirmed', 'partially_fulfilled', 'fulfilled'])->orderByDesc('o.id')->limit(50)->get(['o.reference', 'o.branch_id', 'o.status', 'c.full_name as customer', 'l.sku_snapshot', 'l.description', 'l.quantity', 'l.fulfilled_quantity', 'l.cogs_minor', 'v.id as variant_id']);

        return compact('supply', 'inventory', 'demand', 'fulfillment', 'details');
    }

    private function vendorOutstanding(int $companyId, bool $overdue): int
    {
        $allocations = $this->db->table('ap_payment_allocations')->selectRaw('vendor_bill_id, SUM(allocated_minor) allocated')->groupBy('vendor_bill_id');
        $query = $this->db->table('vendor_bills as b')->leftJoinSub($allocations, 'a', 'a.vendor_bill_id', '=', 'b.id')->where('b.company_id', $companyId)->whereIn('b.status', ['approved', 'partially_paid']);
        if ($overdue) {
            $query->whereDate('b.due_date', '<', now()->toDateString());
        }

        $value = $query->selectRaw('COALESCE(SUM(b.total_minor-COALESCE(a.allocated,0)),0) aggregate')->value('aggregate');

        return is_numeric($value) ? (int) $value : 0;
    }
}
