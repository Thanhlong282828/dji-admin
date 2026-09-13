<?php

namespace Modules\Procurement\Application\Queries;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Procurement\Application\Services\ProcurementExceptionReadService;
use Modules\Procurement\Application\Services\ProcurementReconciliationReadService;
use Modules\Procurement\Application\Services\VendorPerformanceReadService;

final readonly class GetProcurementPageHandler
{
    public function __construct(private ProcurementReconciliationReadService $reconciliation, private ProcurementExceptionReadService $exceptions, private VendorPerformanceReadService $vendorPerformance) {}

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
        $billedAcceptances = DB::table('vendor_bill_lines as vbl')->join('vendor_bills as vb', 'vb.id', '=', 'vbl.vendor_bill_id')->join('service_acceptance_lines as sal', 'sal.id', '=', 'vbl.service_acceptance_line_id')->whereNotIn('vb.status', ['cancelled', 'rejected'])->selectRaw('sal.service_acceptance_id, SUM(vbl.line_total_minor) billed_minor')->groupBy('sal.service_acceptance_id');
        $base['serviceAcceptances'] = DB::table('service_acceptances as sa')->join('purchase_orders as po', 'po.id', '=', 'sa.purchase_order_id')->join('procurement_vendors as v', 'v.id', '=', 'sa.vendor_id')->join('branches as b', 'b.id', '=', 'sa.branch_id')->leftJoinSub($billedAcceptances, 'billed', 'billed.service_acceptance_id', '=', 'sa.id')->where('sa.company_id', $company)->orderByDesc('sa.id')->get(['sa.*', 'po.reference as order_reference', 'v.name as vendor_name', 'b.name as branch_name', DB::raw('COALESCE(billed.billed_minor,0) billed_minor')]);
        $base['serviceAcceptanceLines'] = DB::table('service_acceptance_lines as sal')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')->where('sa.company_id', $company)->orderBy('sal.id')->get(['sal.*']);
        $base['serviceAcceptanceDocuments'] = DB::table('enterprise_documents')->where(['company_id' => $company, 'subject_type' => 'SERVICE_ACCEPTANCE'])->orderByDesc('id')->get(['id', 'subject_id', 'title', 'document_type', 'status', 'current_version_id']);
        $base['serviceAcceptanceWorkflows'] = DB::table('workflow_instances')->where('company_id', $company)->whereIn('subject_type', ['SERVICE_ACCEPTANCE_APPROVAL', 'SERVICE_ACCEPTANCE_REVERSAL_APPROVAL'])->orderByDesc('id')->get(['id', 'subject_type', 'subject_id', 'status', 'engine_status', 'started_at', 'completed_at']);
        $allocations = DB::table('ap_payment_allocations as allocation')->leftJoin('ap_payment_allocation_reversals as reversal', fn (JoinClause $join): JoinClause => $join->on('reversal.ap_payment_allocation_id', '=', 'allocation.id')->where('reversal.effective_at', '<=', now()->endOfDay()->toDateTimeString()))->selectRaw('allocation.vendor_bill_id, SUM(allocation.allocated_minor-COALESCE(reversal.reversed_minor,0)) allocated')->groupBy('allocation.vendor_bill_id');
        $base['bills'] = DB::table('vendor_bills as b')->join('procurement_vendors as v', 'v.id', '=', 'b.vendor_id')->join('branches as branch', 'branch.id', '=', 'b.branch_id')->leftJoin('purchase_orders as purchase_order', 'purchase_order.id', '=', 'b.purchase_order_id')->leftJoinSub($allocations, 'a', 'a.vendor_bill_id', '=', 'b.id')->leftJoin('vendor_bill_reversal_requests as reversal', 'reversal.vendor_bill_id', '=', 'b.id')->where('b.company_id', $company)->orderByDesc('b.id')->get(['b.*', 'v.name as vendor_name', 'branch.name as branch_name', 'purchase_order.reference as purchase_order_reference', 'reversal.id as reversal_request_id', 'reversal.status as reversal_status', 'reversal.requested_by as reversal_requested_by', 'reversal.requested_at as reversal_requested_at', 'reversal.approved_at as reversal_approved_at', 'reversal.effective_at as reversal_effective_at', 'reversal.reversal_journal_id', DB::raw('EXISTS(SELECT 1 FROM vendor_bill_lines service_line WHERE service_line.vendor_bill_id=b.id AND service_line.service_acceptance_line_id IS NOT NULL) as is_service_bill'), DB::raw('CURRENT_DATE as aging_as_of'), DB::raw('COALESCE(a.allocated,0) allocated_minor'), DB::raw('b.total_minor - COALESCE(a.allocated,0) outstanding_minor'), DB::raw("CASE WHEN b.due_date >= CURRENT_DATE THEN 'current' WHEN DATEDIFF(CURRENT_DATE,b.due_date) <= 30 THEN '1-30' WHEN DATEDIFF(CURRENT_DATE,b.due_date) <= 60 THEN '31-60' WHEN DATEDIFF(CURRENT_DATE,b.due_date) <= 90 THEN '61-90' ELSE '90+' END aging_bucket")]);
        $base['payments'] = DB::table('ap_payments as p')->join('procurement_vendors as v', 'v.id', '=', 'p.vendor_id')->where('p.company_id', $company)->orderByDesc('p.id')->get(['p.*', 'v.name as vendor_name']);
        $base['openAccountingPeriods'] = DB::table('accounting_periods')->where(['company_id' => $company, 'status' => 'open'])->orderByDesc('end_date')->get(['id', 'year', 'month', 'end_date']);
        $base['inventorySummary'] = DB::table('inventory_balances as ib')->join('inventory_locations as l', 'l.id', '=', 'ib.inventory_location_id')->join('branches as b', 'b.id', '=', 'l.branch_id')->where('b.company_id', $company)->selectRaw('SUM(ib.on_hand) on_hand, SUM(ib.inventory_value_minor) inventory_value_minor, SUM(CASE WHEN ib.on_hand = 0 THEN 1 ELSE 0 END) out_of_stock')->first();
        if (in_array($query->page, ['reports', 'vendors'], true)) {
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
