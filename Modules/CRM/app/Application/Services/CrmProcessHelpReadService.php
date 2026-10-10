<?php

namespace Modules\CRM\Application\Services;

use App\Models\User;
use App\Support\Erp\ProcessHelpReadService;

final readonly class CrmProcessHelpReadService extends ProcessHelpReadService
{
    /** @return array<string, mixed> */
    public function read(int $companyId, ?int $branchId, User $actor, string $workspace = 'pipeline'): array
    {
        $scope = ['company_id' => $companyId, 'branch_id' => $branchId];
        $pipeline = $this->url($actor, 'crm.view', 'admin.crm.index', $scope);
        $leads = $this->url($actor, 'crm.view', 'admin.crm.leads', $scope);
        $opportunities = $this->url($actor, 'crm.view', 'admin.crm.opportunities', $scope);
        $sales = $this->url($actor, 'sales.view', 'admin.sales.index', $scope);
        $customers = $this->url($actor, 'branch-commerce.view', 'admin.branch-commerce.customers', $scope);
        $treasury = $this->url($actor, 'treasury.view', 'admin.finance.treasury', $scope);
        $journal = $this->url($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.index', $scope);
        $cashFlow = $this->url($actor, 'financial-statement.view', 'admin.finance.financial-statements', $scope);

        return [
            'title' => match ($workspace) {
                'leads' => 'Lead lifecycle', 'opportunities' => 'Opportunity lifecycle', default => 'CRM pipeline to cash'
            },
            'description' => match ($workspace) {
                'leads' => 'Lead capture, qualification và conversion từ nguồn canonical.', 'opportunities' => 'Opportunity stage, value và handoff sang Commerce/Sales.', default => 'Aggregate CRM pipeline từ Lead đến sales handoff.'
            },
            'owner' => 'CRM Module',
            'accounting_impact' => 'Lead, Opportunity và Quotation chưa post GL; Invoice tạo AR; Collection giảm AR; Refund và Treasury tác động cash.',
            'steps' => [
                $this->step('Pipeline', 'CRM Module', 'GetCrmPipelineQuery → CrmPipelineReadService', 'Tổng hợp trạng thái Lead và Opportunity.', 'Không tạo journal.', 'Lead', $pipeline, $this->count('crm_leads', $companyId, $branchId) + $this->count('crm_opportunities', $companyId, $branchId), 'Open Pipeline'),
                $this->step('Lead', 'CRM Module', 'GetLeadWorkspaceQuery → CrmLeadReadService', 'Khách hàng tiềm năng chưa xác nhận.', 'Không tạo journal.', 'Opportunity', $leads, $this->count('crm_leads', $companyId, $branchId), 'Create Lead'),
                $this->step('Opportunity', 'CRM Module', 'GetOpportunityWorkspaceQuery → CrmOpportunityReadService', 'Cơ hội bán hàng đã được đánh giá.', 'Không tạo journal.', 'Customer', $opportunities, $this->count('crm_opportunities', $companyId, $branchId), 'Create Opportunity'),
                $this->step('Customer', 'Commerce Module', 'Customer workspace read model', 'Customer canonical được liên kết khi opportunity đủ điều kiện.', 'Không tạo journal.', 'Quotation', $customers, $this->count('commerce_customers', $companyId), 'Open Customer Workspace'),
                $this->step('Quotation', 'Commerce / Sales', 'GetSalesReceivableQuery → AccountsReceivableReadService', 'Đề xuất giá chính thức cho Customer.', 'Không tạo journal.', 'Sales Order', $sales, $this->count('sales_quotations', $companyId, $branchId), 'Create Quotation'),
                $this->step('Sales Order', 'Commerce / Sales', 'GetSalesReceivableQuery → AccountsReceivableReadService', 'Cam kết bán hàng từ quotation được chấp nhận.', 'Chưa tạo journal.', 'Fulfillment', $sales, $this->count('sales_orders', $companyId, $branchId), 'Create from Quotation'),
                $this->step('Fulfillment', 'Commerce + Inventory', 'AccountsReceivableReadService', 'Giao hàng theo Sales Order.', 'Dr COGS · Cr Inventory.', 'Customer Invoice', $sales, $this->count('sales_fulfillments', $companyId, $branchId), 'Fulfill Sales Order'),
                $this->step('Customer Invoice', 'Commerce Module', 'AccountsReceivableReadService', 'Invoice được phát hành từ Sales Order đã fulfill.', 'Dr AR · Cr Revenue.', 'Collection', $sales, $this->count('customer_invoices', $companyId, $branchId), 'Create Invoice'),
                $this->step('Collection', 'Commerce Module', 'AccountsReceivableReadService', 'Thu tiền và phân bổ vào invoice nguồn.', 'Dr Cash/Clearing · Cr AR.', 'Credit Note', $sales, $this->count('customer_collections', $companyId, $branchId), 'Collect Invoice'),
                $this->step('Credit Note', 'Commerce Module', 'CustomerCreditNoteService', 'Điều chỉnh invoice có lineage nguồn.', 'Giảm AR hoặc tạo customer credit liability.', 'Customer Refund', $sales, $this->count('customer_credit_notes', $companyId, $branchId), 'Create Credit Note'),
                $this->step('Customer Refund', 'Commerce Module', 'GetCustomerRefundWorkspaceQuery → CustomerRefundReadService', 'Hoàn customer credit liability đã được duyệt.', 'Dr Customer Credit Liability · Cr Cash/Bank.', 'Treasury', $sales, $this->count('customer_refunds', $companyId, $branchId), 'Create Refund'),
                $this->step('Treasury', 'Finance / Treasury', 'GetTreasuryCashPositionQuery', 'Theo dõi dòng tiền vào và ra canonical.', 'Tăng/giảm cash và clearing.', 'Accounting Journal', $treasury, $this->count('treasury_transactions', $companyId, $branchId), 'Post upstream transaction'),
                $this->step('Accounting Journal', 'Finance Module', 'AccountingJournalQuery', 'Journal cân bằng giữ source type và source id.', 'Ghi nhận AR, revenue, COGS, liability và cash.', 'Cash Flow', $journal, $this->count('payroll_accounting_journals', $companyId, $branchId), 'Post upstream document'),
                $this->step('Cash Flow', 'Finance Module', 'FinancialStatementReadService', 'Báo cáo dòng tiền từ Treasury và posted GL.', 'Phân loại inflow và outflow.', 'Financial reporting', $cashFlow, $this->count('treasury_transactions', $companyId, $branchId), 'Open Cash Flow'),
            ],
        ];
    }
}
