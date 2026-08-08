<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Generator;
use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Domain\Repositories\PayrollExportReadRepositoryInterface;

final readonly class EloquentPayrollExportReadRepository implements PayrollExportReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string, int|string|null> $filters @return Generator<int, list<int|string|null>> */
    public function payrollItems(int $companyId, int $runId, array $filters): Generator
    {
        $employeeId = $this->filterInt($filters, 'employee_id');
        $organizationUnit = $this->filterString($filters, 'organization_unit');
        $jobPosition = $this->filterString($filters, 'job_position');
        $calculationStatus = $this->filterString($filters, 'calculation_status');
        $validationStatus = $this->filterString($filters, 'validation_status');
        $currency = $this->filterString($filters, 'currency_code');
        $normalizedCurrency = $currency === null ? null : strtoupper($currency);
        $query = $this->db->table('payroll_items as i')
            ->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->leftJoin('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')
            ->leftJoin('payroll_input_snapshots as s', 's.id', '=', 'r.payroll_input_snapshot_id')
            ->where('r.company_id', $companyId)->where('i.company_id', $companyId)->where('r.id', $runId)
            ->when($employeeId !== null, fn ($q) => $q->where('i.employee_id', $employeeId))
            ->when($organizationUnit !== null, fn ($q) => $q->where('i.organization_unit_snapshot', $organizationUnit))
            ->when($jobPosition !== null, fn ($q) => $q->where('i.job_position_snapshot', $jobPosition))
            ->when($calculationStatus !== null, fn ($q) => $q->where('i.calculation_status', $calculationStatus))
            ->when($validationStatus !== null, fn ($q) => $q->where('i.validation_status', $validationStatus))
            ->when($normalizedCurrency !== null, fn ($q) => $q->where('i.currency_code', $normalizedCurrency))
            ->orderBy('i.employee_number_snapshot')->orderBy('i.id')
            ->select(['r.run_number', 'p.period_start', 'p.period_end', 's.snapshot_version', 'i.employee_number_snapshot', 'i.employee_name_snapshot', 'i.organization_unit_snapshot', 'i.job_position_snapshot', 'i.currency_code', 'i.scheduled_minutes', 'i.worked_minutes', 'i.paid_leave_minutes', 'i.unpaid_leave_minutes', 'i.approved_overtime_minutes', 'i.gross_amount_minor', 'i.deduction_amount_minor', 'i.employer_contribution_amount_minor', 'i.net_amount_minor', 'i.calculation_status', 'i.validation_status', 'i.calculation_version', 'i.calculated_at']);

        foreach ($query->cursor() as $row) {
            yield [$this->stringValue($row->run_number), $this->stringValue($row->period_start), $this->stringValue($row->period_end), $this->intValue($row->snapshot_version), $this->stringValue($row->employee_number_snapshot), $this->stringValue($row->employee_name_snapshot), $this->nullableString($row->organization_unit_snapshot), $this->nullableString($row->job_position_snapshot), $this->stringValue($row->currency_code), $this->intValue($row->scheduled_minutes), $this->intValue($row->worked_minutes), $this->intValue($row->paid_leave_minutes), $this->intValue($row->unpaid_leave_minutes), $this->intValue($row->approved_overtime_minutes), $this->intValue($row->gross_amount_minor), $this->intValue($row->deduction_amount_minor), $this->intValue($row->employer_contribution_amount_minor), $this->intValue($row->net_amount_minor), $this->stringValue($row->calculation_status), $this->stringValue($row->validation_status), $this->intValue($row->calculation_version), $this->nullableString($row->calculated_at)];
        }
    }

    /** @param array<string, int|string|null> $filters @return Generator<int, list<int|string|null>> */
    public function components(int $companyId, int $runId, array $filters): Generator
    {
        $employeeId = $this->filterInt($filters, 'employee_id');
        $componentId = $this->filterInt($filters, 'salary_component_id');
        $category = $this->filterString($filters, 'category');
        $calculationType = $this->filterString($filters, 'calculation_type');
        $validationStatus = $this->filterString($filters, 'validation_status');
        $query = $this->db->table('payroll_item_components as c')
            ->join('payroll_runs as r', 'r.id', '=', 'c.payroll_run_id')
            ->join('payroll_items as i', 'i.id', '=', 'c.payroll_item_id')
            ->where('r.company_id', $companyId)->where('c.company_id', $companyId)->where('r.id', $runId)
            ->when($employeeId !== null, fn ($q) => $q->where('i.employee_id', $employeeId))
            ->when($componentId !== null, fn ($q) => $q->where('c.salary_component_id', $componentId))
            ->when($category !== null, fn ($q) => $q->where('c.category', $category))
            ->when($calculationType !== null, fn ($q) => $q->where('c.calculation_type', $calculationType))
            ->when($validationStatus !== null, fn ($q) => $q->where('i.validation_status', $validationStatus))
            ->orderBy('i.employee_number_snapshot')->orderBy('c.calculation_order')->orderBy('c.id')
            ->select(['r.run_number', 'i.employee_number_snapshot', 'i.employee_name_snapshot', 'c.component_code_snapshot', 'c.component_name_snapshot', 'c.category', 'c.calculation_type', 'c.source_type', 'c.quantity_basis_points', 'c.rate_minor', 'c.percentage_basis_points', 'c.base_amount_minor', 'c.amount_minor', 'c.currency_code', 'c.calculation_order', 'i.calculation_version']);

        foreach ($query->cursor() as $row) {
            yield [$this->stringValue($row->run_number), $this->stringValue($row->employee_number_snapshot), $this->stringValue($row->employee_name_snapshot), $this->stringValue($row->component_code_snapshot), $this->stringValue($row->component_name_snapshot), $this->stringValue($row->category), $this->stringValue($row->calculation_type), $this->stringValue($row->source_type), $this->nullableInt($row->quantity_basis_points), $this->nullableInt($row->rate_minor), $this->nullableInt($row->percentage_basis_points), $this->nullableInt($row->base_amount_minor), $this->intValue($row->amount_minor), $this->stringValue($row->currency_code), $this->intValue($row->calculation_order), $this->intValue($row->calculation_version)];
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit(ltrim($value, '-')) ? (int) $value : 0);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : $this->intValue($value);
    }

    /** @param array<string, int|string|null> $filters */
    private function filterInt(array $filters, string $key): ?int
    {
        $value = $filters[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /** @param array<string, int|string|null> $filters */
    private function filterString(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
