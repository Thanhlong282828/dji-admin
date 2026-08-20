<?php

namespace App\Support\Compensation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class CompensationReadService
{
    public function __construct(private PositionCompensationReference $references) {}

    /**
     * @param  list<int>  $employeeIds
     * @return array<int, CompensationView>
     */
    public function currentForEmployees(int $companyId, array $employeeIds, ?string $date = null): array
    {
        if ($employeeIds === []) {
            return [];
        }
        $effective = $date ?? now()->toDateString();
        $rows = DB::table('employee_salary_component_assignments as a')
            ->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')
            ->where('a.company_id', $companyId)->whereIn('a.employee_id', $employeeIds)
            ->where('a.status', 'active')->where('c.status', 'active')
            ->whereDate('a.effective_from', '<=', $effective)
            ->where(fn (Builder $query): Builder => $query->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $effective))
            ->get(['a.employee_id', 'a.amount_minor', 'a.currency_code', 'a.effective_from', 'a.source', 'c.code']);

        /** @var array<int, list<array{amount_minor:int,currency_code:string,effective_from:string,source:string,code:string}>> $grouped */
        $grouped = [];
        foreach ($rows as $raw) {
            $row = (array) $raw;
            if (! is_numeric($row['employee_id'] ?? null) || ! is_numeric($row['amount_minor'] ?? null) || ! is_string($row['currency_code'] ?? null) || ! is_string($row['effective_from'] ?? null) || ! is_string($row['source'] ?? null) || ! is_string($row['code'] ?? null)) {
                continue;
            }
            $grouped[(int) $row['employee_id']][] = ['amount_minor' => (int) $row['amount_minor'], 'currency_code' => $row['currency_code'], 'effective_from' => $row['effective_from'], 'source' => $row['source'], 'code' => $row['code']];
        }
        $result = [];
        foreach ($grouped as $employeeId => $assignments) {
            $result[$employeeId] = $this->fromAssignments($assignments);
        }

        return $result;
    }

    /** @return list<array<string, int|string|null>> */
    public function positionReferences(int $companyId): array
    {
        $rows = DB::table('job_positions as p')->join('organization_units as u', 'u.id', '=', 'p.organization_unit_id')
            ->where('p.company_id', $companyId)->whereBetween('p.career_level', [1, 6])
            ->orderBy('u.name')->orderBy('p.career_level')->orderBy('p.title')
            ->get(['p.id', 'p.title', 'p.career_level', 'p.organization_unit_id', 'u.name as unit_name']);

        $result = [];
        foreach ($rows as $raw) {
            $row = (array) $raw;
            if (! is_numeric($row['id'] ?? null) || ! is_numeric($row['career_level'] ?? null) || ! is_numeric($row['organization_unit_id'] ?? null) || ! is_string($row['title'] ?? null) || ! is_string($row['unit_name'] ?? null)) {
                continue;
            }
            $reference = $this->references->resolve((int) $row['career_level'], $row['unit_name'], $row['title'])->toArray();
            $result[] = $reference + ['position_id' => (int) $row['id'], 'position_title' => $row['title'], 'organization_unit_id' => (int) $row['organization_unit_id'], 'unit_name' => $row['unit_name'], 'career_level' => (int) $row['career_level']];
        }

        return $result;
    }

    /** @param list<array{amount_minor:int,currency_code:string,effective_from:string,source:string,code:string}> $assignments */
    private function fromAssignments(array $assignments): CompensationView
    {
        $base = 0;
        $allowance = 0;
        $kpi = 0;
        $other = 0;
        $currency = 'VND';
        $source = 'payroll_assignment';
        $effectiveFrom = null;
        foreach ($assignments as $assignment) {
            $code = strtoupper($assignment['code']);
            $amount = $assignment['amount_minor'];
            if (str_contains($code, 'BASE')) {
                $base += $amount;
            } elseif (str_contains($code, 'KPI')) {
                $kpi += $amount;
            } elseif (str_contains($code, 'ALLOW')) {
                $allowance += $amount;
            } else {
                $other += $amount;
            }
            $currency = $assignment['currency_code'];
            $source = $assignment['source'];
            $from = $assignment['effective_from'];
            $effectiveFrom = $effectiveFrom === null || $from > $effectiveFrom ? $from : $effectiveFrom;
        }

        return new CompensationView($base, $allowance, $kpi, $other, $currency, $source, $effectiveFrom);
    }
}
