<?php

namespace Modules\Commerce\Application\Services;

use Illuminate\Support\Facades\DB;

final readonly class AccountsReceivableReadService
{
    /** @return array<string, mixed> */
    public function read(int $company): array
    {
        $allocated = DB::table('customer_collection_allocations')->selectRaw('customer_invoice_id, SUM(allocated_minor) allocated')->groupBy('customer_invoice_id');

        return [
            'customers' => DB::table('commerce_customers')->where('company_id', $company)->get(),
            'branches' => DB::table('branches')->where('company_id', $company)->whereNull('deleted_at')->get(),
            'variants' => DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $company)->get(['v.id', 'v.sku', 'v.price_minor', 'p.name']),
            'locations' => DB::table('inventory_locations as l')->join('branches as b', 'b.id', '=', 'l.branch_id')->where('b.company_id', $company)->get(['l.id', 'l.name', 'l.branch_id']),
            'quotations' => DB::table('sales_quotations')->where('company_id', $company)->latest('id')->get(),
            'orders' => DB::table('sales_orders')->where('company_id', $company)->latest('id')->get(),
            'order_lines' => DB::table('sales_order_lines as l')->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')->where('o.company_id', $company)->whereColumn('l.fulfilled_quantity', '<', 'l.quantity')->get(['l.*', 'o.branch_id']),
            'invoices' => DB::table('customer_invoices as i')
                ->join('commerce_customers as customer', 'customer.id', '=', 'i.customer_id')
                ->join('branches as branch', 'branch.id', '=', 'i.branch_id')
                ->leftJoin('sales_orders as sales_order', 'sales_order.id', '=', 'i.sales_order_id')
                ->leftJoinSub($allocated, 'a', 'a.customer_invoice_id', '=', 'i.id')
                ->where('i.company_id', $company)
                ->select(['i.*', 'customer.full_name as customer_name', 'branch.name as branch_name', 'sales_order.reference as sales_order_reference', DB::raw('CURRENT_DATE as aging_as_of'), DB::raw('COALESCE(a.allocated,0) allocated_minor'), DB::raw('i.total_minor-COALESCE(a.allocated,0) outstanding_minor'), DB::raw('GREATEST(DATEDIFF(CURRENT_DATE,i.due_date),0) days_overdue'), DB::raw("CASE WHEN i.due_date>=CURRENT_DATE THEN 'current' WHEN DATEDIFF(CURRENT_DATE,i.due_date)<=30 THEN '1-30' WHEN DATEDIFF(CURRENT_DATE,i.due_date)<=60 THEN '31-60' WHEN DATEDIFF(CURRENT_DATE,i.due_date)<=90 THEN '61-90' ELSE '90+' END aging_bucket"), DB::raw("CASE WHEN i.status IN ('issued','partially_paid') AND i.due_date<CURRENT_DATE THEN 'overdue' ELSE i.status END derived_status")])
                ->latest('i.id')->get(),
            'collections' => DB::table('customer_collections')->where('company_id', $company)->latest('id')->get(),
        ];
    }
}
