<?php

namespace Modules\Procurement\Application\Queries;

use Illuminate\Support\Facades\DB;

final class GetProcurementPageHandler
{
    /** @return array<string,mixed> */
    public function handle(GetProcurementPageQuery $query): array
    {
        $company = $query->companyId;
        $base = [
            'page' => $query->page,
            'vendors' => DB::table('procurement_vendors')->where('company_id', $company)->orderBy('name')->get(),
            'branches' => DB::table('branches')->where('company_id', $company)->whereNull('deleted_at')->orderBy('name')->get(['id', 'code', 'name']),
            'costCenters' => DB::table('cost_centers')->where('company_id', $company)->orderBy('name')->get(['id', 'code', 'name']),
            'budgetAccounts' => DB::table('accounting_accounts')->where('company_id', $company)->whereIn('code', ['1200', '1300', '5100'])->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'variants' => DB::table('product_variants as pv')->join('products as p', 'p.id', '=', 'pv.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $company)->whereNull('p.deleted_at')->orderBy('p.name')->get(['pv.id', 'pv.sku', 'p.name']),
            'locations' => DB::table('inventory_locations as l')->join('branches as b', 'b.id', '=', 'l.branch_id')->where('b.company_id', $company)->whereNull('b.deleted_at')->orderBy('b.name')->get(['l.id', 'l.branch_id', 'l.name', 'b.name as branch_name']),
        ];
        $base['requests'] = DB::table('purchase_requests as r')->join('branches as b', 'b.id', '=', 'r.branch_id')->join('users as u', 'u.id', '=', 'r.requester_id')->where('r.company_id', $company)->orderByDesc('r.id')->get(['r.*', 'b.name as branch_name', 'u.name as requester_name']);
        $base['orders'] = DB::table('purchase_orders as o')->join('procurement_vendors as v', 'v.id', '=', 'o.vendor_id')->join('branches as b', 'b.id', '=', 'o.branch_id')->where('o.company_id', $company)->orderByDesc('o.id')->get(['o.*', 'v.name as vendor_name', 'b.name as branch_name']);
        $base['orderLines'] = DB::table('purchase_order_lines as l')->join('purchase_orders as o', 'o.id', '=', 'l.purchase_order_id')->where('o.company_id', $company)->orderBy('l.id')->get(['l.*']);
        $base['receipts'] = DB::table('procurement_goods_receipts as g')->join('purchase_orders as o', 'o.id', '=', 'g.purchase_order_id')->join('branches as b', 'b.id', '=', 'g.branch_id')->where('g.company_id', $company)->orderByDesc('g.id')->get(['g.*', 'o.reference as order_reference', 'b.name as branch_name']);
        $base['bills'] = DB::table('vendor_bills as b')->join('procurement_vendors as v', 'v.id', '=', 'b.vendor_id')->join('branches as branch', 'branch.id', '=', 'b.branch_id')->leftJoin('purchase_orders as purchase_order', 'purchase_order.id', '=', 'b.purchase_order_id')->leftJoinSub(DB::table('ap_payment_allocations')->selectRaw('vendor_bill_id, SUM(allocated_minor) allocated')->groupBy('vendor_bill_id'), 'a', 'a.vendor_bill_id', '=', 'b.id')->where('b.company_id', $company)->orderByDesc('b.id')->get(['b.*', 'v.name as vendor_name', 'branch.name as branch_name', 'purchase_order.reference as purchase_order_reference', DB::raw('CURRENT_DATE as aging_as_of'), DB::raw('COALESCE(a.allocated,0) allocated_minor'), DB::raw('b.total_minor - COALESCE(a.allocated,0) outstanding_minor'), DB::raw("CASE WHEN b.due_date >= CURRENT_DATE THEN 'current' WHEN DATEDIFF(CURRENT_DATE,b.due_date) <= 30 THEN '1-30' WHEN DATEDIFF(CURRENT_DATE,b.due_date) <= 60 THEN '31-60' WHEN DATEDIFF(CURRENT_DATE,b.due_date) <= 90 THEN '61-90' ELSE '90+' END aging_bucket")]);
        $base['payments'] = DB::table('ap_payments as p')->join('procurement_vendors as v', 'v.id', '=', 'p.vendor_id')->where('p.company_id', $company)->orderByDesc('p.id')->get(['p.*', 'v.name as vendor_name']);
        $base['inventorySummary'] = DB::table('inventory_balances as ib')->join('inventory_locations as l', 'l.id', '=', 'ib.inventory_location_id')->join('branches as b', 'b.id', '=', 'l.branch_id')->where('b.company_id', $company)->selectRaw('SUM(ib.on_hand) on_hand, SUM(ib.inventory_value_minor) inventory_value_minor, SUM(CASE WHEN ib.on_hand = 0 THEN 1 ELSE 0 END) out_of_stock')->first();

        return $base;
    }
}
