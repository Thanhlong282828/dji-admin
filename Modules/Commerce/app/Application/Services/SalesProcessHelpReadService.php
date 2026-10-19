<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use App\Support\Erp\ProcessHelpReadService;
use Illuminate\Support\Facades\Schema;

final readonly class SalesProcessHelpReadService extends ProcessHelpReadService
{
    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        $scope = ['company_id' => $companyId, 'branch_id' => $branchId];
        $sales = $this->url($actor, 'sales.view', 'admin.sales.index', $scope);
        $crm = $this->url($actor, 'crm.view', 'admin.crm.index', $scope);
        $customers = $this->url($actor, 'branch-commerce.view', 'admin.branch-commerce.customers', $scope);
        $treasury = $this->url($actor, 'treasury.view', 'admin.finance.treasury', $scope);
        $journals = $this->url($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.index', $scope);
        $cashFlow = $this->url($actor, 'payroll.accounting.view', 'admin.finance.financial-statements', $scope);
        $quotations = $this->count('sales_quotations', $companyId, $branchId);
        $orders = $this->count('sales_orders', $companyId, $branchId);
        $fulfillments = $this->count('sales_fulfillments', $companyId, $branchId);
        $invoices = $this->count('customer_invoices', $companyId, $branchId);
        $collections = $this->count('customer_collections', $companyId, $branchId);
        $creditNotes = $this->count('customer_credit_notes', $companyId, $branchId);
        $refunds = $this->count('customer_refunds', $companyId, $branchId);
        $invoiceMoney = $this->invoiceMoneySummary($companyId, $branchId);

        return [
            'title' => 'Sales CRM to cash workflow',
            'description' => 'Lineage từ CRM capability đến Cash Flow, nối canonical Lead và Opportunity sang sell-side ERP.',
            'owner' => 'Commerce / Sales + Finance',
            'accounting_impact' => 'Quotation và Order chưa post GL; Fulfillment tạo COGS; Invoice tạo AR; Collection giảm AR; Credit/Refund điều chỉnh liability và cash.',
            'metrics' => [
                ['label' => 'Sales Orders', 'value' => $orders, 'meta' => 'Nguồn: sales_orders canonical', 'url' => $sales, 'action' => 'View Orders ↗'],
                ['label' => $invoiceMoney['gross_label'], 'value' => $invoiceMoney['gross_value'], 'suffix' => $invoiceMoney['suffix'], 'meta' => $invoiceMoney['meta'], 'url' => $sales, 'action' => 'View Invoices ↗'],
                ['label' => $invoiceMoney['ar_label'], 'value' => $invoiceMoney['ar_value'], 'suffix' => $invoiceMoney['suffix'], 'meta' => $invoiceMoney['ar_meta'], 'url' => $sales, 'action' => 'View AR Aging ↗'],
                ['label' => 'Customers', 'value' => $this->count('commerce_customers', $companyId), 'meta' => 'Nguồn: commerce_customers', 'url' => $customers, 'action' => 'Open Customer Workspace ↗'],
            ],
            'steps' => [
                $this->step('Lead', 'CRM Module', 'GetLeadWorkspaceQuery → CrmPipelineReadService', 'Khách hàng tiềm năng chưa xác nhận.', 'Không tạo journal.', 'Opportunity', $crm, $this->count('crm_leads', $companyId, $branchId), 'Create Lead'),
                $this->step('Opportunity', 'CRM Module', 'GetOpportunityWorkspaceQuery → CrmPipelineReadService', 'Cơ hội bán hàng đã được đánh giá.', 'Không tạo journal.', 'Quotation', $crm, $this->count('crm_opportunities', $companyId, $branchId), 'Create Opportunity'),
                $this->step('Quotation', 'Commerce Module', 'GetSalesReceivableQuery → AccountsReceivableReadService', 'Báo giá cho Customer, Branch và SKU thật.', 'Không tạo journal.', 'Sales Order', $sales, $quotations, 'Create Quotation'),
                $this->step('Sales Order', 'Commerce Module', 'GetSalesReceivableQuery → AccountsReceivableReadService', 'Đơn bán được convert từ accepted Quotation.', 'Chưa tạo journal.', 'Fulfillment', $sales, $orders, 'Create from accepted Quotation'),
                $this->step('Fulfillment', 'Commerce + Inventory', 'GetSalesReceivableQuery → AccountsReceivableReadService', 'Xuất hàng theo Sales Order line và kho thật.', 'Dr COGS · Cr Inventory.', 'Customer Invoice', $sales, $fulfillments, 'Fulfill confirmed Sales Order'),
                $this->step('Customer Invoice', 'Commerce + Finance', 'GetSalesReceivableQuery → AccountsReceivableReadService; CustomerInvoiceFxRecognitionService', 'Invoice giữ transaction currency. Cùng functional currency dùng identity rate 1, functional amount bằng transaction amount và không tạo governed FX snapshot. Ngoại tệ bắt buộc direct active spot rate đúng invoice date; thiếu evidence thì issuance fail closed và rollback — không inverse, closing hay latest fallback.', 'Journal dùng functional currency: Dr Accounts Receivable · Cr Revenue. Invoice recognition không phải realized FX; realized gain/loss chỉ phát sinh ở settlement/Collection (AR-FX-3). Tax FX hiện fail closed.', 'Collection', $sales, $invoices, 'Create from fulfilled Sales Order'),
                $this->step('Collection', 'Commerce + Finance + Treasury', 'AllocateCustomerCollectionCommand → SalesReceivableService → ArFxSettlementEvidenceService', 'Collection phải cùng transaction currency với Invoice. Foreign allocation dùng direct spot rate tại collection date, immutable settlement snapshot và proportional carrying-basis release; thiếu rate/evidence thì toàn bộ transaction rollback.', 'Journal dùng functional currency: Dr Cash/Clearing, Cr AR carrying basis và ghi realized FX gain/loss đúng một lần. Treasury receipt bắt buộc account cùng collection currency; cross-currency settlement chưa hỗ trợ.', 'Customer Credit Note', $sales, $collections, 'Collect issued Invoice'),
                $this->step('Customer Credit Note', 'Commerce Module', 'GetSalesReceivableQuery → CustomerCreditNoteService', 'Điều chỉnh Invoice và giữ lineage line nguồn.', 'Giảm AR hoặc tạo Customer Credit Liability.', 'Customer Refund', $sales, $creditNotes, 'Create from Customer Invoice'),
                $this->step('Customer Refund', 'Commerce Module', 'GetCustomerRefundWorkspaceQuery → CustomerRefundReadService', 'Hoàn liability phát sinh từ posted Credit Note.', 'Dr Customer Credit Liability · Cr Cash/Bank.', 'Treasury', $sales, $refunds, 'Create from available credit'),
                $this->step('Treasury', 'Finance / Treasury', 'GetTreasuryCashPositionQuery → TreasuryCashPositionReadService', 'Cash movement canonical cho collection/refund.', 'Tăng cash khi collection; giảm cash khi refund.', 'Accounting Journal', $treasury, $this->count('treasury_transactions', $companyId, $branchId), 'Post upstream payment'),
                $this->step('Accounting Journal', 'Finance Module', 'AccountingJournalQuery → SourceAccountingPostingService', 'Journal cân bằng giữ source type/id.', 'Cập nhật AR, revenue, COGS, liability và cash.', 'Cash Flow', $journals, $this->count('payroll_accounting_journals', $companyId, $branchId), 'Post upstream document'),
                $this->step('Cash Flow', 'Finance Module', 'FinancialStatementReadService', 'Dòng tiền đọc từ Treasury và posted GL.', 'Phân loại inflow collection và outflow refund.', 'Financial reporting', $cashFlow, $this->count('treasury_transactions', $companyId, $branchId), 'Post upstream payment'),
            ],
        ];
    }

    private function sum(string $table, string $column, int $companyId, ?int $branchId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id') || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) $this->scoped($table, $companyId, $branchId)->sum($column);
    }

    private function outstandingAr(int $companyId, ?int $branchId): int
    {
        if (! Schema::hasTable('customer_invoices')) {
            return 0;
        }
        $allocations = $this->db->table('customer_collection_allocations')->selectRaw('customer_invoice_id, SUM(allocated_minor) allocated_minor')->groupBy('customer_invoice_id');
        $query = $this->scoped('customer_invoices', $companyId, $branchId)->leftJoinSub($allocations, 'allocation', 'allocation.customer_invoice_id', '=', 'customer_invoices.id');
        if (Schema::hasTable('customer_credit_notes')) {
            $credits = $this->db->table('customer_credit_notes')->where('status', 'posted')->selectRaw('customer_invoice_id, SUM(ar_reduction_minor) ar_reduction_minor')->groupBy('customer_invoice_id');
            $query->leftJoinSub($credits, 'credit', 'credit.customer_invoice_id', '=', 'customer_invoices.id');
        }
        $creditExpression = Schema::hasTable('customer_credit_notes') ? 'COALESCE(credit.ar_reduction_minor,0)' : '0';

        $value = $query->selectRaw("SUM(GREATEST(customer_invoices.total_minor-COALESCE(allocation.allocated_minor,0)-{$creditExpression},0)) outstanding_minor")->value('outstanding_minor');

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;
    }

    /** @return array{gross_label:string,gross_value:int,ar_label:string,ar_value:int,suffix:string,meta:string,ar_meta:string} */
    private function invoiceMoneySummary(int $companyId, ?int $branchId): array
    {
        $currencies = $this->scoped('customer_invoices', $companyId, $branchId)->select('currency')->distinct()->orderBy('currency')->pluck('currency')->filter(static fn (mixed $currency): bool => is_string($currency) && $currency !== '')->values();
        if ($currencies->count() > 1) {
            $labels = $currencies->implode(', ');

            return ['gross_label' => 'Invoice currency groups', 'gross_value' => $currencies->count(), 'ar_label' => 'AR currency groups', 'ar_value' => $currencies->count(), 'suffix' => ' currencies', 'meta' => 'MIXED — xem theo transaction currency: '.$labels, 'ar_meta' => 'Không cộng chéo tiền tệ; xem AR theo từng currency: '.$labels];
        }
        $currency = $currencies->first();
        $suffix = is_string($currency) ? ' '.$currency : '';

        return ['gross_label' => 'Issued invoice gross', 'gross_value' => $this->sum('customer_invoices', 'total_minor', $companyId, $branchId), 'ar_label' => 'Outstanding AR', 'ar_value' => $this->outstandingAr($companyId, $branchId), 'suffix' => $suffix, 'meta' => 'Nguồn: customer_invoices'.($suffix === '' ? '' : ' · transaction currency'), 'ar_meta' => 'Invoice − allocated collection − AR credit'.($suffix === '' ? '' : ' · transaction currency')];
    }
}
