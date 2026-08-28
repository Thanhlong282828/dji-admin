<?php

namespace App\Support\Finance;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class BranchDashboardReadService
{
    public function __construct(private ConnectionInterface $db, private BranchFinancialPerformanceService $financials) {}

    /** @return array{branches:int,assigned_employees:int,unassigned_employees:int,financials:array<string,int>|null,financial_trend:list<array{month:string,revenue:int,payroll:int,operating:int,profit:int}>} */
    public function summary(int $companyId, bool $canViewFinancials): array
    {
        $branches = $this->db->table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->count();
        $assignedEmployees = $this->db->table('employee_branch_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->where('a.company_id', $companyId)->where('e.company_id', $companyId)->where('a.assignment_type', 'primary')
            ->whereDate('a.effective_from', '<=', today())->where(static fn (Builder $query): Builder => $query->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', today()))
            ->distinct()->count('a.employee_id');
        $activeEmployees = $this->db->table('employees')->where('company_id', $companyId)->whereIn('employment_status', ['active', 'probation', 'preboarding'])->whereNull('deleted_at')->count();

        $trend = [];
        if ($canViewFinancials) {
            foreach (range(5, 0) as $monthsAgo) {
                $month = now()->startOfMonth()->subMonths($monthsAgo)->format('Y-m');
                $totals = $this->financials->snapshot($companyId, $month)['totals'];
                $trend[] = ['month' => $month, 'revenue' => $totals['revenue'], 'payroll' => $totals['payroll'], 'operating' => $totals['operating'] + $totals['rent'] + $totals['depreciation'], 'profit' => $totals['profit']];
            }
        }

        return [
            'branches' => $branches,
            'assigned_employees' => $assignedEmployees,
            'unassigned_employees' => max(0, $activeEmployees - $assignedEmployees),
            'financials' => $canViewFinancials ? $this->financials->snapshot($companyId, now()->format('Y-m'))['totals'] : null,
            'financial_trend' => $trend,
        ];
    }
}
