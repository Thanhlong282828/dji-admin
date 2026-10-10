<?php

namespace Modules\Commerce\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Company\Domain\Enums\SupportedCurrency;

final readonly class AccountsReceivableReadService
{
    /** @return array<string, mixed> */
    public function read(int $company): array
    {
        $allocated = DB::table('customer_collection_allocations')->selectRaw('customer_invoice_id, SUM(allocated_minor) allocated')->groupBy('customer_invoice_id');
        $creditSchemaReady = Schema::hasTable('customer_credit_notes');
        $credits = $creditSchemaReady
            ? DB::table('customer_credit_notes')->where('status', 'posted')->selectRaw('customer_invoice_id, SUM(total_minor) credited, SUM(ar_reduction_minor) ar_reduced, SUM(customer_credit_liability_minor) customer_credit')->groupBy('customer_invoice_id')
            : null;
        $invoices = DB::table('customer_invoices as i')
            ->join('commerce_customers as customer', 'customer.id', '=', 'i.customer_id')
            ->join('branches as branch', 'branch.id', '=', 'i.branch_id')
            ->leftJoin('sales_orders as sales_order', 'sales_order.id', '=', 'i.sales_order_id')
            ->leftJoin('customer_invoice_fx_recognitions as fx_recognition', 'fx_recognition.customer_invoice_id', '=', 'i.id')
            ->leftJoinSub($allocated, 'a', 'a.customer_invoice_id', '=', 'i.id');
        if ($credits !== null) {
            $invoices->leftJoinSub($credits, 'credit', 'credit.customer_invoice_id', '=', 'i.id');
        }
        $credited = $creditSchemaReady ? 'COALESCE(credit.credited,0)' : '0';
        $arReduced = $creditSchemaReady ? 'COALESCE(credit.ar_reduced,0)' : '0';
        $customerCredit = $creditSchemaReady ? 'COALESCE(credit.customer_credit,0)' : '0';
        $fulfillments = DB::table('sales_fulfillments')->selectRaw('sales_order_id, COUNT(*) fulfillment_count, GROUP_CONCAT(reference ORDER BY id SEPARATOR ", ") fulfillment_references')->groupBy('sales_order_id');
        $invoiceTrace = DB::table('customer_invoices as trace_invoice')->leftJoin('customer_collection_allocations as trace_allocation', 'trace_allocation.customer_invoice_id', '=', 'trace_invoice.id')->leftJoin('customer_collections as trace_collection', 'trace_collection.id', '=', 'trace_allocation.customer_collection_id')->selectRaw('trace_invoice.sales_order_id, MAX(trace_invoice.id) invoice_id, MAX(trace_invoice.invoice_number) invoice_number, MAX(trace_invoice.accounting_journal_id) invoice_journal_id, GROUP_CONCAT(DISTINCT trace_collection.reference ORDER BY trace_collection.id SEPARATOR ", ") collection_references')->groupBy('trace_invoice.sales_order_id');

        return [
            'currencies' => SupportedCurrency::values(),
            'customers' => DB::table('commerce_customers')->where('company_id', $company)->get(),
            'branches' => DB::table('branches')->where('company_id', $company)->whereNull('deleted_at')->get(),
            'variants' => DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $company)->get(['v.id', 'v.sku', 'v.price_minor', 'p.name']),
            'locations' => DB::table('inventory_locations as l')->join('branches as b', 'b.id', '=', 'l.branch_id')->where('b.company_id', $company)->get(['l.id', 'l.name', 'l.branch_id']),
            'quotations' => DB::table('sales_quotations')->where('company_id', $company)->latest('id')->get(),
            'orders' => DB::table('sales_orders as sales_order')->leftJoin('sales_quotations as quotation', 'quotation.id', '=', 'sales_order.quotation_id')->leftJoinSub($fulfillments, 'fulfillment_trace', 'fulfillment_trace.sales_order_id', '=', 'sales_order.id')->leftJoinSub($invoiceTrace, 'invoice_trace', 'invoice_trace.sales_order_id', '=', 'sales_order.id')->where('sales_order.company_id', $company)->select(['sales_order.*', 'quotation.reference as quotation_reference', 'fulfillment_trace.fulfillment_count', 'fulfillment_trace.fulfillment_references', 'invoice_trace.invoice_id', 'invoice_trace.invoice_number', 'invoice_trace.invoice_journal_id', 'invoice_trace.collection_references'])->latest('sales_order.id')->get(),
            'order_lines' => DB::table('sales_order_lines as l')->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')->where('o.company_id', $company)->whereColumn('l.fulfilled_quantity', '<', 'l.quantity')->get(['l.*', 'o.branch_id']),
            'invoices' => $invoices
                ->where('i.company_id', $company)
                ->select(['i.*', 'customer.full_name as customer_name', 'branch.name as branch_name', 'sales_order.reference as sales_order_reference', 'fx_recognition.id as fx_recognition_id', 'fx_recognition.functional_currency', 'fx_recognition.functional_amount_minor', 'fx_recognition.rate_decimal as recognition_rate_decimal', 'fx_recognition.rate_date as recognition_rate_date', 'fx_recognition.source_reference as recognition_source_reference', DB::raw('CURRENT_DATE as aging_as_of'), DB::raw('COALESCE(a.allocated,0) allocated_minor'), DB::raw($credited.' credited_minor'), DB::raw($customerCredit.' customer_credit_liability_minor'), DB::raw('GREATEST(i.total_minor-COALESCE(a.allocated,0)-'.$arReduced.',0) outstanding_minor'), DB::raw('GREATEST(i.total_minor-'.$credited.',0) remaining_creditable_minor'), DB::raw('GREATEST(DATEDIFF(CURRENT_DATE,i.due_date),0) days_overdue'), DB::raw("CASE WHEN i.due_date>=CURRENT_DATE THEN 'current' WHEN DATEDIFF(CURRENT_DATE,i.due_date)<=30 THEN '1-30' WHEN DATEDIFF(CURRENT_DATE,i.due_date)<=60 THEN '31-60' WHEN DATEDIFF(CURRENT_DATE,i.due_date)<=90 THEN '61-90' ELSE '90+' END aging_bucket"), DB::raw("CASE WHEN GREATEST(i.total_minor-COALESCE(a.allocated,0)-{$arReduced},0)=0 THEN 'settled' WHEN i.due_date<CURRENT_DATE THEN 'overdue' ELSE i.status END derived_status")])
                ->latest('i.id')->get(),
            'invoice_lines' => DB::table('customer_invoice_lines as line')->join('customer_invoices as invoice', 'invoice.id', '=', 'line.customer_invoice_id')->where('invoice.company_id', $company)->get(['line.*']),
            'credit_notes' => $creditSchemaReady ? DB::table('customer_credit_notes as note')->join('customer_invoices as invoice', 'invoice.id', '=', 'note.customer_invoice_id')->where('note.company_id', $company)->orderByDesc('note.id')->get(['note.*', 'invoice.invoice_number']) : collect(),
            'credit_note_schema_status' => $creditSchemaReady ? 'READY' : 'SCHEMA_UPGRADE_REQUIRED',
            'treasury_accounts' => DB::table('treasury_financial_accounts')->where(['company_id' => $company, 'status' => 'active'])->whereIn('type', ['cash', 'bank', 'payment_clearing'])->orderBy('code')->get(['id', 'branch_id', 'code', 'name', 'currency']),
            'collections' => DB::table('customer_collections as collection')
                ->leftJoin('customer_collection_allocations as allocation', 'allocation.customer_collection_id', '=', 'collection.id')
                ->leftJoin('customer_collection_allocation_fx_settlements as settlement', 'settlement.customer_collection_allocation_id', '=', 'allocation.id')
                ->leftJoin('treasury_transactions as treasury', 'treasury.id', '=', 'collection.treasury_transaction_id')
                ->where('collection.company_id', $company)->latest('collection.id')
                ->get(['collection.*', 'allocation.customer_invoice_id', 'settlement.rate_decimal as settlement_rate_decimal', 'settlement.source_reference as settlement_source_reference', 'settlement.settlement_functional_minor', 'settlement.released_carrying_minor', 'settlement.realized_fx_minor', 'settlement.functional_currency', 'treasury.financial_account_id']),
        ];
    }
}
