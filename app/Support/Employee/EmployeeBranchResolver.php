<?php

namespace App\Support\Employee;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Validation\ValidationException;

final readonly class EmployeeBranchResolver
{
    public function __construct(private ConnectionInterface $db) {}

    public function resolve(int $companyId, int $employeeId, CarbonImmutable $at, bool $required = false): ?int
    {
        $resolved = $this->resolveMany($companyId, [$employeeId], $at);
        $branch = $resolved[$employeeId] ?? null;
        if ($branch !== null) {
            return $branch;
        }

        if ($required) {
            $accessCount = $this->accessCounts($companyId, [$employeeId])[$employeeId] ?? 0;
            throw ValidationException::withMessages(['branch' => $accessCount === 0 ? 'Nhân viên chưa được gán chi nhánh vận hành.' : 'Nhân viên có nhiều Branch Access; cần primary branch assignment hiệu lực.']);
        }

        return null;
    }

    /**
     * Resolve effective operational branches with bounded queries.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, int|null>
     */
    public function resolveMany(int $companyId, array $employeeIds, CarbonImmutable $at): array
    {
        $employeeIds = array_values(array_unique(array_filter($employeeIds, static fn (int $id): bool => $id > 0)));
        if ($employeeIds === []) {
            return [];
        }

        $resolved = array_fill_keys($employeeIds, null);
        $assignments = $this->db->table('employee_branch_assignments as a')->join('branches as b', 'b.id', '=', 'a.branch_id')
            ->where('a.company_id', $companyId)->whereIn('a.employee_id', $employeeIds)->where('a.assignment_type', 'primary')
            ->whereDate('a.effective_from', '<=', $at->toDateString())->where(fn (Builder $query): Builder => $query->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $at->toDateString()))
            ->where('b.company_id', $companyId)->whereNull('b.deleted_at')->orderBy('a.employee_id')->orderByDesc('a.effective_from')->get(['a.employee_id', 'a.branch_id']);
        foreach ($assignments as $assignment) {
            if (is_numeric($assignment->employee_id ?? null) && is_numeric($assignment->branch_id ?? null) && $resolved[(int) $assignment->employee_id] === null) {
                $resolved[(int) $assignment->employee_id] = (int) $assignment->branch_id;
            }
        }

        $unresolved = array_keys(array_filter($resolved, static fn (?int $branchId): bool => $branchId === null));
        if ($unresolved === []) {
            return $resolved;
        }

        $access = $this->db->table('employees as e')->join('branch_user_access as bua', 'bua.user_id', '=', 'e.user_id')->join('branches as b', 'b.id', '=', 'bua.branch_id')
            ->where('e.company_id', $companyId)->whereIn('e.id', $unresolved)->where('b.company_id', $companyId)->whereNull('b.deleted_at')
            ->groupBy('e.id')->havingRaw('COUNT(DISTINCT b.id) = 1')->select('e.id')->selectRaw('MIN(b.id) as branch_id')->get();
        foreach ($access as $row) {
            if (is_numeric($row->id ?? null) && is_numeric($row->branch_id ?? null)) {
                $resolved[(int) $row->id] = (int) $row->branch_id;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, int>
     */
    private function accessCounts(int $companyId, array $employeeIds): array
    {
        $counts = $this->db->table('employees as e')->leftJoin('branch_user_access as bua', 'bua.user_id', '=', 'e.user_id')->leftJoin('branches as b', function (JoinClause $join) use ($companyId): void {
            $join->on('b.id', '=', 'bua.branch_id')->where('b.company_id', $companyId)->whereNull('b.deleted_at');
        })->where('e.company_id', $companyId)->whereIn('e.id', $employeeIds)->groupBy('e.id')->select('e.id')->selectRaw('COUNT(DISTINCT b.id) as access_count')->get();

        $result = [];
        foreach ($counts as $row) {
            if (is_numeric($row->id ?? null) && is_numeric($row->access_count ?? null)) {
                $result[(int) $row->id] = (int) $row->access_count;
            }
        }

        return $result;
    }
}
