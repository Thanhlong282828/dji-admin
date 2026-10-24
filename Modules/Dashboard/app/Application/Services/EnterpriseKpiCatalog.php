<?php

namespace Modules\Dashboard\Application\Services;

final class EnterpriseKpiCatalog
{
    /** @return list<array{code:string,label:string,domain:string,unit:string,source:string,filters:list<string>,route:string,status:string}> */
    public function definitions(): array
    {
        return [
            $this->kpi('revenue.recognized', 'Doanh thu ghi nhận', 'Sales/Finance', 'VND', 'Customer Invoice + recognized Marketplace/POS revenue', ['company', 'branch', 'period', 'channel'], 'admin.finance.overview'),
            $this->kpi('finance.cogs', 'COGS', 'Finance', 'VND', 'Posted GL via FinanceOverviewReadService', ['company', 'branch', 'period'], 'admin.finance.financial-statements'),
            $this->kpi('finance.operating_result', 'Kết quả hoạt động', 'Finance', 'VND', 'Posted GL via FinanceOverviewReadService', ['company', 'branch', 'period'], 'admin.finance.overview'),
            $this->kpi('payroll.gross', 'Payroll Gross', 'Payroll', 'VND', 'Finalized Payroll Run period', ['company', 'period'], 'admin.payroll.runs.index'),
            $this->kpi('ar.outstanding', 'AR Outstanding', 'Sales/AR', 'VND', 'Issued Customer Invoice less allocations', ['company', 'branch', 'period'], 'admin.sales.index'),
            $this->kpi('ap.outstanding', 'AP Outstanding', 'Procurement/AP', 'VND', 'Approved Vendor Bill less allocations', ['company', 'branch', 'period'], 'admin.procurement.payables'),
            $this->kpi('cash.net_movement', 'Net Cash Movement', 'Treasury', 'VND', 'Recorded Treasury transactions', ['company', 'branch', 'period'], 'admin.finance.treasury'),
            $this->kpi('inventory.value', 'Inventory Value', 'Inventory', 'VND', 'Canonical Inventory balances', ['company', 'branch'], 'admin.supply-chain.index'),
            $this->kpi('inventory.available', 'Available Stock', 'Inventory', 'quantity', 'On hand minus reserved', ['company', 'branch'], 'admin.supply-chain.index'),
            $this->kpi('procurement.open_po', 'Open PO', 'Procurement', 'count', 'Open Purchase Orders', ['company', 'branch'], 'admin.procurement.orders'),
            $this->kpi('headcount.active', 'Active Headcount', 'Employee', 'count', 'Effective Employee status', ['company'], 'admin.employees.index'),
            $this->kpi('attendance.late_count', 'Late Records', 'Attendance', 'count', 'Attendance work_date', ['company', 'branch', 'period'], 'admin.attendance.index'),
            $this->kpi('sales.orders', 'Sales Orders', 'Commerce', 'count', 'ERP Sales + Marketplace + POS canonical documents', ['company', 'branch', 'period', 'channel'], 'admin.sales.index'),
            $this->kpi('crm.customers', 'Customers', 'Commerce/CRM', 'count', 'CustomerMetricsService', ['company'], 'admin.branch-commerce.customers'),
            $this->kpi('data_quality.score', 'Data Quality', 'Control', 'percent', 'DataQualityAuditService', ['company'], 'admin.operations.data-quality'),
        ];
    }

    /**
     * @param  list<string>  $filters
     * @return array{code:string,label:string,domain:string,unit:string,source:string,filters:list<string>,route:string,status:string}
     */
    private function kpi(string $code, string $label, string $domain, string $unit, string $source, array $filters, string $route): array
    {
        return compact('code', 'label', 'domain', 'unit', 'source', 'filters', 'route') + ['status' => 'IMPLEMENTED'];
    }
}
