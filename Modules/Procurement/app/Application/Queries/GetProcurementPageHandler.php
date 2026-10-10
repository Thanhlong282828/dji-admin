<?php

namespace Modules\Procurement\Application\Queries;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\Procurement\Application\Services\ProcurementExceptionReadService;
use Modules\Procurement\Application\Services\ProcurementReconciliationReadService;
use Modules\Procurement\Application\Services\VendorPerformanceReadService;
use Modules\Procurement\Application\Services\VendorRefundConfigurationReadinessService;

final readonly class GetProcurementPageHandler
{
    public function __construct(private ProcurementReconciliationReadService $reconciliation, private ProcurementExceptionReadService $exceptions, private VendorPerformanceReadService $vendorPerformance, private VendorRefundConfigurationReadinessService $vendorRefundReadiness) {}

    /** @return array<string,mixed> */
    public function handle(GetProcurementPageQuery $query): array
    {
        $company = $query->companyId;
        $schema = [
            'goods_returns' => Schema::hasTable('procurement_goods_returns') && Schema::hasTable('procurement_goods_return_lines'),
            'supplier_credits' => Schema::hasTable('supplier_credit_notes') && Schema::hasTable('supplier_credit_note_lines') && Schema::hasTable('vendor_bill_receipt_allocations'),
            'vendor_refunds' => Schema::hasTable('vendor_refund_receivables') && Schema::hasTable('supplier_refund_receipts'),
            'treasury_accounts' => Schema::hasTable('treasury_financial_accounts'),
            'ap_fx_recognition' => Schema::hasTable('supplier_invoice_fx_recognitions'),
            'ap_fx_settlement' => Schema::hasTable('supplier_payment_fx_settlements'),
            'inventory_receipt_fx' => Schema::hasTable('inventory_receipt_fx_valuations'),
        ];
        $base = [
            'page' => $query->page,
            'schemaReadiness' => $schema,
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
        $receiptValuations = $schema['inventory_receipt_fx']
            ? DB::table('inventory_receipt_fx_valuations')->selectRaw('goods_receipt_id, MAX(transaction_currency) transaction_currency, SUM(foreign_amount_minor) foreign_amount_minor, MAX(functional_currency) functional_currency, SUM(functional_amount_minor) functional_amount_minor, MAX(rate_decimal) rate_decimal, MAX(rate_type) rate_type, MAX(recognition_date) recognition_date, MAX(accounting_journal_id) accounting_journal_id, COUNT(*) valuation_lines')->groupBy('goods_receipt_id')
            : DB::table('procurement_goods_receipts')->whereRaw('1=0')->selectRaw('id as goods_receipt_id, NULL transaction_currency, NULL foreign_amount_minor, NULL functional_currency, NULL functional_amount_minor, NULL rate_decimal, NULL rate_type, NULL recognition_date, NULL accounting_journal_id, 0 valuation_lines');
        $base['receipts'] = DB::table('procurement_goods_receipts as g')->join('purchase_orders as o', 'o.id', '=', 'g.purchase_order_id')->join('branches as b', 'b.id', '=', 'g.branch_id')->leftJoinSub($receiptValuations, 'fx', 'fx.goods_receipt_id', '=', 'g.id')->where('g.company_id', $company)->orderByDesc('g.id')->get(['g.*', 'o.reference as order_reference', 'o.currency as order_currency', 'b.name as branch_name', 'fx.transaction_currency', 'fx.foreign_amount_minor', 'fx.functional_currency', 'fx.functional_amount_minor', 'fx.rate_decimal', 'fx.rate_type', 'fx.recognition_date', 'fx.accounting_journal_id', 'fx.valuation_lines']);
        $postedReturns = $schema['goods_returns']
            ? DB::table('procurement_goods_return_lines as line')->join('procurement_goods_returns as header', 'header.id', '=', 'line.goods_return_id')->where('header.status', 'posted')->selectRaw('line.goods_receipt_line_id, SUM(line.quantity) returned_quantity')->groupBy('line.goods_receipt_line_id')
            : DB::table('procurement_goods_receipt_lines')->whereRaw('1=0')->selectRaw('id as goods_receipt_line_id, 0 as returned_quantity');
        $base['returnableReceiptLines'] = DB::table('procurement_goods_receipt_lines as line')->join('procurement_goods_receipts as receipt', 'receipt.id', '=', 'line.goods_receipt_id')->join('purchase_orders as po', 'po.id', '=', 'receipt.purchase_order_id')->join('procurement_vendors as vendor', 'vendor.id', '=', 'po.vendor_id')->leftJoinSub($postedReturns, 'returned', 'returned.goods_receipt_line_id', '=', 'line.id')->where(['receipt.company_id' => $company, 'receipt.status' => 'received'])->whereRaw('line.received_quantity > COALESCE(returned.returned_quantity,0)')->orderByDesc('receipt.id')->get(['line.id', 'line.received_quantity', 'line.unit_cost_minor', 'line.line_value_minor', 'receipt.reference as receipt_reference', 'po.reference as order_reference', 'vendor.name as vendor_name', DB::raw('COALESCE(returned.returned_quantity,0) returned_quantity'), DB::raw('line.received_quantity-COALESCE(returned.returned_quantity,0) returnable_quantity')]);
        $base['goodsReturns'] = $schema['goods_returns'] ? DB::table('procurement_goods_returns as goods_return')->join('procurement_vendors as vendor', 'vendor.id', '=', 'goods_return.vendor_id')->join('purchase_orders as po', 'po.id', '=', 'goods_return.purchase_order_id')->join('procurement_goods_receipts as receipt', 'receipt.id', '=', 'goods_return.goods_receipt_id')->where('goods_return.company_id', $company)->orderByDesc('goods_return.id')->get(['goods_return.*', 'vendor.name as vendor_name', 'po.reference as order_reference', 'receipt.reference as receipt_reference']) : collect();
        $postedCredits = $schema['supplier_credits']
            ? DB::table('supplier_credit_notes')->where('status', 'posted')->selectRaw('vendor_bill_id, SUM(ap_reduction_minor) credited_minor')->groupBy('vendor_bill_id')
            : DB::table('vendor_bills')->whereRaw('1=0')->selectRaw('id as vendor_bill_id, 0 as credited_minor');
        $base['supplierCredits'] = $schema['supplier_credits'] && $schema['goods_returns'] ? DB::table('supplier_credit_notes as credit')->join('procurement_vendors as vendor', 'vendor.id', '=', 'credit.vendor_id')->join('vendor_bills as bill', 'bill.id', '=', 'credit.vendor_bill_id')->join('procurement_goods_returns as goods_return', 'goods_return.id', '=', 'credit.goods_return_id')->where('credit.company_id', $company)->orderByDesc('credit.id')->get(['credit.*', 'vendor.name as vendor_name', 'bill.invoice_number', 'goods_return.return_number']) : collect();
        $hasVendorRefundSchema = $schema['vendor_refunds'] && $schema['supplier_credits'];
        $base['vendorRefundReceivables'] = $hasVendorRefundSchema ? DB::table('vendor_refund_receivables as receivable')->join('supplier_credit_notes as credit', 'credit.id', '=', 'receivable.supplier_credit_note_id')->join('procurement_vendors as vendor', 'vendor.id', '=', 'receivable.vendor_id')->where('receivable.company_id', $company)->orderByDesc('receivable.id')->get(['receivable.*', 'credit.credit_number', 'vendor.name as vendor_name', DB::raw('receivable.original_minor-receivable.refunded_minor remaining_minor')]) : collect();
        $base['supplierRefundReceipts'] = $hasVendorRefundSchema ? DB::table('supplier_refund_receipts as refund')->join('vendor_refund_receivables as receivable', 'receivable.id', '=', 'refund.vendor_refund_receivable_id')->join('procurement_vendors as vendor', 'vendor.id', '=', 'refund.vendor_id')->where('refund.company_id', $company)->orderByDesc('refund.id')->get(['refund.*', 'vendor.name as vendor_name', 'receivable.original_minor', 'receivable.refunded_minor']) : collect();
        $base['treasuryFinancialAccounts'] = $schema['treasury_accounts'] ? DB::table('treasury_financial_accounts')->where(['company_id' => $company, 'status' => 'active'])->orderBy('name')->get(['id', 'branch_id', 'code', 'name', 'currency']) : collect();
        $base['vendorRefundReadiness'] = $this->vendorRefundReadiness->forCompany($company, now()->toDateString(), 'VND');
        $base['creditableReturnLines'] = $schema['supplier_credits'] && $schema['goods_returns'] ? DB::table('procurement_goods_return_lines as return_line')->join('procurement_goods_returns as goods_return', 'goods_return.id', '=', 'return_line.goods_return_id')->join('vendor_bill_receipt_allocations as allocation', 'allocation.goods_receipt_line_id', '=', 'return_line.goods_receipt_line_id')->join('vendor_bill_lines as bill_line', 'bill_line.id', '=', 'allocation.vendor_bill_line_id')->join('vendor_bills as bill', 'bill.id', '=', 'bill_line.vendor_bill_id')->leftJoinSub($postedCredits, 'posted_credit', 'posted_credit.vendor_bill_id', '=', 'bill.id')->where(['goods_return.company_id' => $company, 'goods_return.status' => 'approved'])->whereNotIn('bill.status', ['draft', 'cancelled', 'rejected', 'reversed'])->orderByDesc('return_line.id')->get(['return_line.id as goods_return_line_id', 'return_line.quantity as returned_quantity', 'return_line.line_value_minor', 'allocation.id as vendor_bill_receipt_allocation_id', 'allocation.quantity as allocated_quantity', 'bill.id as vendor_bill_id', 'bill.invoice_number', 'bill.total_minor', 'goods_return.return_number', DB::raw('COALESCE(posted_credit.credited_minor,0) credited_minor')]) : collect();
        $billedAcceptances = DB::table('vendor_bill_lines as vbl')->join('vendor_bills as vb', 'vb.id', '=', 'vbl.vendor_bill_id')->join('service_acceptance_lines as sal', 'sal.id', '=', 'vbl.service_acceptance_line_id')->whereNotIn('vb.status', ['cancelled', 'rejected'])->selectRaw('sal.service_acceptance_id, SUM(vbl.line_total_minor) billed_minor')->groupBy('sal.service_acceptance_id');
        $base['serviceAcceptances'] = DB::table('service_acceptances as sa')->join('purchase_orders as po', 'po.id', '=', 'sa.purchase_order_id')->join('procurement_vendors as v', 'v.id', '=', 'sa.vendor_id')->join('branches as b', 'b.id', '=', 'sa.branch_id')->leftJoinSub($billedAcceptances, 'billed', 'billed.service_acceptance_id', '=', 'sa.id')->where('sa.company_id', $company)->orderByDesc('sa.id')->get(['sa.*', 'po.reference as order_reference', 'v.name as vendor_name', 'b.name as branch_name', DB::raw('COALESCE(billed.billed_minor,0) billed_minor')]);
        $base['serviceAcceptanceLines'] = DB::table('service_acceptance_lines as sal')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')->where('sa.company_id', $company)->orderBy('sal.id')->get(['sal.*']);
        $base['serviceAcceptanceDocuments'] = DB::table('enterprise_documents')->where(['company_id' => $company, 'subject_type' => 'SERVICE_ACCEPTANCE'])->orderByDesc('id')->get(['id', 'subject_id', 'title', 'document_type', 'status', 'current_version_id']);
        $base['serviceAcceptanceWorkflows'] = DB::table('workflow_instances')->where('company_id', $company)->whereIn('subject_type', ['SERVICE_ACCEPTANCE_APPROVAL', 'SERVICE_ACCEPTANCE_REVERSAL_APPROVAL'])->orderByDesc('id')->get(['id', 'subject_type', 'subject_id', 'status', 'engine_status', 'started_at', 'completed_at']);
        $allocations = DB::table('ap_payment_allocations as allocation')->leftJoin('ap_payment_allocation_reversals as reversal', fn (JoinClause $join): JoinClause => $join->on('reversal.ap_payment_allocation_id', '=', 'allocation.id')->where('reversal.effective_at', '<=', now()->endOfDay()->toDateTimeString()))->selectRaw('allocation.vendor_bill_id, SUM(allocation.allocated_minor-COALESCE(reversal.reversed_minor,0)) allocated')->groupBy('allocation.vendor_bill_id');
        $bills = DB::table('vendor_bills as b')->join('procurement_vendors as v', 'v.id', '=', 'b.vendor_id')->join('branches as branch', 'branch.id', '=', 'b.branch_id')->leftJoin('purchase_orders as purchase_order', 'purchase_order.id', '=', 'b.purchase_order_id')->leftJoinSub($allocations, 'a', 'a.vendor_bill_id', '=', 'b.id')->leftJoinSub($postedCredits, 'credit_total', 'credit_total.vendor_bill_id', '=', 'b.id')->leftJoin('vendor_bill_reversal_requests as reversal', 'reversal.vendor_bill_id', '=', 'b.id');
        if ($schema['ap_fx_recognition']) {
            $bills->leftJoin('supplier_invoice_fx_recognitions as fx', 'fx.vendor_bill_id', '=', 'b.id');
        }
        $billColumns = ['b.*', 'v.name as vendor_name', 'branch.name as branch_name', 'purchase_order.reference as purchase_order_reference', 'reversal.id as reversal_request_id', 'reversal.status as reversal_status', 'reversal.requested_by as reversal_requested_by', 'reversal.requested_at as reversal_requested_at', 'reversal.approved_at as reversal_approved_at', 'reversal.effective_at as reversal_effective_at', 'reversal.reversal_journal_id', DB::raw('EXISTS(SELECT 1 FROM vendor_bill_lines service_line WHERE service_line.vendor_bill_id=b.id AND service_line.service_acceptance_line_id IS NOT NULL) as is_service_bill'), DB::raw('CURRENT_DATE as aging_as_of'), DB::raw('COALESCE(a.allocated,0) allocated_minor'), DB::raw('COALESCE(credit_total.credited_minor,0) credited_minor'), DB::raw('b.total_minor - COALESCE(a.allocated,0) - COALESCE(credit_total.credited_minor,0) outstanding_minor'), DB::raw("CASE WHEN b.due_date >= CURRENT_DATE THEN 'current' WHEN DATEDIFF(CURRENT_DATE,b.due_date) <= 30 THEN '1-30' WHEN DATEDIFF(CURRENT_DATE,b.due_date) <= 60 THEN '31-60' WHEN DATEDIFF(CURRENT_DATE,b.due_date) <= 90 THEN '61-90' ELSE '90+' END aging_bucket")];
        if ($schema['ap_fx_recognition']) {
            $billColumns = [...$billColumns, 'fx.functional_currency', 'fx.functional_amount_minor', 'fx.rate_decimal as fx_rate_decimal', 'fx.rate_date as fx_rate_date', 'fx.rate_type as fx_rate_type', 'fx.source_reference as fx_source_reference', 'fx.fx_rate_snapshot_id', 'fx.accounting_journal_id as fx_accounting_journal_id'];
        }
        $base['bills'] = $bills->where('b.company_id', $company)->orderByDesc('b.id')->get($billColumns);
        $payments = DB::table('ap_payments as p')->join('procurement_vendors as v', 'v.id', '=', 'p.vendor_id')->leftJoin('ap_payment_allocations as allocation', 'allocation.payment_id', '=', 'p.id');
        if ($schema['ap_fx_settlement']) {
            $payments->leftJoin('supplier_payment_fx_settlements as settlement', 'settlement.ap_payment_allocation_id', '=', 'allocation.id');
            $payments->leftJoin('treasury_financial_accounts as settlement_account', 'settlement_account.id', '=', 'settlement.treasury_financial_account_id');
        }
        $paymentColumns = ['p.*', 'v.name as vendor_name', 'allocation.vendor_bill_id'];
        if ($schema['ap_fx_settlement']) {
            $paymentColumns = [...$paymentColumns, 'settlement.settlement_functional_minor', 'settlement.released_carrying_minor', 'settlement.realized_fx_minor', 'settlement.functional_currency', 'settlement.rate_decimal', 'settlement.rate_date', 'settlement.rate_type', 'settlement.source_reference', 'settlement.accounting_journal_id', 'settlement.treasury_transaction_id', 'settlement_account.code as treasury_account_code', 'settlement_account.name as treasury_account_name'];
        }
        $base['payments'] = $payments->where('p.company_id', $company)->orderByDesc('p.id')->get($paymentColumns);
        $base['openAccountingPeriods'] = DB::table('accounting_periods')->where(['company_id' => $company, 'status' => 'open'])->orderByDesc('end_date')->get(['id', 'year', 'month', 'end_date']);
        $base['inventorySummary'] = DB::table('inventory_balances as ib')->join('inventory_locations as l', 'l.id', '=', 'ib.inventory_location_id')->join('branches as b', 'b.id', '=', 'l.branch_id')->where('b.company_id', $company)->selectRaw('SUM(ib.on_hand) on_hand, SUM(ib.inventory_value_minor) inventory_value_minor, SUM(CASE WHEN ib.on_hand = 0 THEN 1 ELSE 0 END) out_of_stock')->first();
        if (in_array($query->page, ['reports', 'vendors'], true) && $schema['goods_returns'] && $schema['supplier_credits']) {
            $filters = [
                'date_from' => is_string($query->filters['date_from'] ?? null) ? $query->filters['date_from'] : now()->startOfYear()->toDateString(),
                'date_to' => is_string($query->filters['date_to'] ?? null) ? $query->filters['date_to'] : now()->toDateString(),
                'branch_id' => is_numeric($query->filters['branch_id'] ?? null) ? (int) $query->filters['branch_id'] : null,
                'cost_center_id' => is_numeric($query->filters['cost_center_id'] ?? null) ? (int) $query->filters['cost_center_id'] : null,
                'vendor_id' => is_numeric($query->filters['vendor_id'] ?? null) ? (int) $query->filters['vendor_id'] : null,
                'classification' => in_array($query->filters['classification'] ?? null, ['inventory', 'operating_expense', 'asset'], true) ? $query->filters['classification'] : null,
                'currency' => 'VND',
            ];
            $base['reconciliation'] = $this->reconciliation->read($company, $filters);
            $exceptionFilters = $filters + [
                'exception_type' => is_string($query->filters['exception_type'] ?? null) ? $query->filters['exception_type'] : null,
                'severity' => in_array($query->filters['severity'] ?? null, ['INFO', 'WARNING', 'CRITICAL'], true) ? $query->filters['severity'] : null,
                'business_status' => is_string($query->filters['business_status'] ?? null) ? $query->filters['business_status'] : null,
            ];
            $exceptions = $this->exceptions->read($company, $exceptionFilters, $base['reconciliation']);
            if ($query->page === 'reports') {
                $exceptions['rows'] = array_values(array_filter($exceptions['rows'], static fn (array $row): bool => is_string($row['permission'] ?? null) && Gate::allows($row['permission'])));
                $exceptions['counts'] = collect($exceptions['rows'])->countBy('exception_type')->all();
                $exceptions['total'] = count($exceptions['rows']);
                $base['exceptions'] = $exceptions;
                $base['exceptionFilters'] = $exceptionFilters;
            } else {
                $sort = in_array($query->filters['sort'] ?? null, ['name', 'po_count', 'ordered', 'outstanding', 'exception_count'], true) ? (string) $query->filters['sort'] : 'ordered';
                $direction = ($query->filters['direction'] ?? null) === 'asc' ? 'asc' : 'desc';
                $base['vendorPerformance'] = $this->vendorPerformance->read($company, $filters + ['sort' => $sort, 'direction' => $direction], $exceptions['rows']);
            }
        }

        return $base;
    }
}
