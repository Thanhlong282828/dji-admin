<?php

namespace Modules\Dashboard\Application\Services;

use Modules\Finance\Application\Services\ApprovalInboxService;
use Modules\Finance\Application\Services\ExecutiveFinanceReadService;
use Modules\Maintenance\Application\Services\MaintenanceReadService;
use Modules\Project\Application\Services\ProjectReadService;

final readonly class EnterpriseExecutiveDashboardReadService
{
    public function __construct(private EnterpriseAnalyticsReadService $analytics, private ProjectReadService $projects, private MaintenanceReadService $maintenance, private ApprovalInboxService $approvals, private ExecutiveFinanceReadService $executiveFinance) {}

    /** @return array<string,mixed> */
    public function read(int $companyId, int $actorId, ?int $branchId, string $dateFrom, string $dateTo): array
    {
        $source = $this->analytics->read($companyId, $branchId, $dateFrom, $dateTo);
        $finance = $this->array($source, 'finance');
        $income = $this->array($finance, 'income');
        $cash = $this->array($finance, 'cash_flow');
        $supply = $this->array($source, 'supply_chain');
        $inventory = $this->array($supply, 'inventory');
        $series = $this->rows($source['series'] ?? null);
        $project = $this->projects->executiveSummary($companyId);
        $maintenance = $this->maintenance->executiveSummary($companyId);
        // The executive card remains a bounded aggregate; granular reversal work is projected by the canonical Approval Inbox.
        $approvals = $this->approvals->read($companyId, $actorId, false);
        $phase3 = $this->executiveFinance->read($companyId, $branchId, $dateFrom, $dateTo);

        return [
            'filters' => $this->array($source, 'filters'),
            'branches' => $finance['branches'] ?? [],
            'kpis' => [
                $this->kpi('Doanh thu', $income['revenue'] ?? 0, 'PASS', 'Posted General Ledger', 'admin.finance.overview', 'payroll.accounting.view'),
                $this->kpi('Lợi nhuận gộp', $income['gross_profit'] ?? 0, 'PASS', 'Financial Statements', 'admin.finance.financial-statements', 'payroll.accounting.view'),
                $this->kpi('Kết quả vận hành', $income['profit'] ?? 0, 'PASS', 'Financial Statements', 'admin.finance.financial-statements', 'payroll.accounting.view'),
                $this->kpi('Dòng tiền thuần', $cash['net_movement'] ?? 0, 'PARTIAL', 'Treasury movements; thiếu opening cash', 'admin.finance.treasury', 'treasury.view'),
                $this->kpi('Phải thu', $finance['ar_outstanding'] ?? 0, 'PASS', 'Customer Invoice / Allocation', 'admin.sales.index', 'sales.view'),
                $this->kpi('Phải trả', $finance['ap_outstanding'] ?? 0, 'PASS', 'Vendor Bill / AP Allocation', 'admin.procurement.payables', 'vendor-bill.view'),
                $this->kpi('Giá trị tồn kho', $inventory['inventory_value_minor'] ?? 0, 'PASS', 'Inventory valuation', 'admin.supply-chain.index', 'inventory.view'),
                $this->kpi('Chi phí Payroll', $income['payroll'] ?? 0, 'PASS', 'Posted Payroll journal', 'admin.payroll.runs.index', 'payroll.run.view'),
            ],
            'financial_series' => array_map(fn (array $row): array => ['period' => $this->text($row['month'] ?? null), 'revenue' => $this->integer($row['revenue'] ?? null)], $series),
            'cash_series' => array_map(fn (array $row): array => ['period' => $this->text($row['month'] ?? null), 'inflow' => $this->integer($row['cash_in'] ?? null), 'outflow' => $this->integer($row['cash_out'] ?? null), 'net' => $this->integer($row['cash_in'] ?? null) - $this->integer($row['cash_out'] ?? null)], $series),
            'working_capital' => ['ar' => $this->integer($finance['ar_outstanding'] ?? null), 'ap' => $this->integer($finance['ap_outstanding'] ?? null)],
            'aging' => $this->array($finance, 'aging'),
            'phase3' => $phase3,
            'cost_structure' => ['payroll' => $this->integer($income['payroll'] ?? null), 'operating' => $this->integer($income['operating_expense'] ?? null), 'depreciation' => $this->integer($income['depreciation'] ?? null), 'cogs' => $this->integer($income['cogs'] ?? null)],
            'people' => $this->array($source, 'people'),
            'crm' => $this->array($source, 'crm'),
            'supply_chain' => $supply,
            'panels' => ['project' => $project, 'maintenance' => $maintenance],
            'exceptions' => [
                ['label' => 'Data Quality', 'value' => null, 'severity' => 'INFO', 'route' => 'admin.operations.data-quality', 'permission' => 'operations.view'],
                ['label' => 'Phê duyệt chờ xử lý', 'value' => count($approvals['pending']), 'severity' => 'WARNING', 'route' => 'admin.approvals.index', 'permission' => 'approval.inbox.view'],
                ['label' => 'AR quá hạn', 'value' => $this->overdue($this->array($this->array($finance, 'aging'), 'ar')), 'severity' => 'WARNING', 'route' => 'admin.sales.index', 'permission' => 'sales.view'],
                ['label' => 'AP quá hạn', 'value' => $this->overdue($this->array($this->array($finance, 'aging'), 'ap')), 'severity' => 'WARNING', 'route' => 'admin.procurement.payables', 'permission' => 'vendor-bill.view'],
                ['label' => 'Dự án rủi ro / trễ', 'value' => $this->integer($project['at_risk'] ?? null) + $this->integer($project['delayed'] ?? null), 'severity' => 'WARNING', 'route' => 'admin.projects.overview', 'permission' => 'project.view'],
                ['label' => 'Work Order quá hạn', 'value' => $this->integer($maintenance['overdue'] ?? null), 'severity' => 'WARNING', 'route' => 'admin.maintenance.overview', 'permission' => 'maintenance.view'],
                ['label' => 'SKU hết hàng', 'value' => $this->integer($inventory['out_of_stock'] ?? null), 'severity' => 'INFO', 'route' => 'admin.supply-chain.index', 'permission' => 'inventory.view'],
            ],
            'limitations' => [
                'financial_trend' => 'Revenue trend có lịch sử canonical; Gross Profit và Operating Result chỉ hiển thị KPI GL của kỳ vì chưa có owner-provided monthly statement series.',
                'cash' => 'PARTIAL — không khẳng định closing cash khi thiếu opening balance canonical.',
                'inventory' => $this->array($source, 'limitations')['inventory_trend'] ?? 'NOT_ENOUGH_EVIDENCE',
                'project' => 'PARTIAL — Project Revenue/Billing chưa được triển khai.',
                'maintenance' => 'PARTIAL — MTTR/MTBF chưa đủ evidence; chi phí chỉ tính khi có chứng từ canonical.',
                'forecast' => 'AR/AP là nghĩa vụ theo ngày đến hạn, không phải dòng tiền chắc chắn. Không dự báo doanh thu, payroll hay closing cash khi thiếu bằng chứng.',
            ],
        ];
    }

    /** @return array{label:string,value:int,status:string,source:string,route:string,permission:string} */
    private function kpi(string $label, mixed $value, string $status, string $source, string $route, string $permission): array
    {
        return compact('label', 'status', 'source', 'route', 'permission') + ['value' => is_numeric($value) ? (int) $value : 0];
    }

    /**
     * @param  array<mixed, mixed>  $source
     * @return array<string, mixed>
     */
    private function array(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (! is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $nestedKey => $nestedValue) {
            if (is_string($nestedKey)) {
                $result[$nestedKey] = $nestedValue;
            }
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $result[] = $this->array(['row' => $row], 'row');
            }
        }

        return $result;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** @param array<string,mixed> $aging */
    private function overdue(array $aging): int
    {
        return $this->integer($aging['1_30'] ?? null) + $this->integer($aging['31_60'] ?? null) + $this->integer($aging['61_90'] ?? null) + $this->integer($aging['90_plus'] ?? null);
    }
}
