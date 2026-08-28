<?php

namespace Modules\Attendance\Application\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AttendanceOperationsReadService
{
    /** @param array<string,mixed> $filters
     * @return array{rows:LengthAwarePaginator<int,object>,metrics:array{late_occurrences:int,late_employees:int,late_minutes:int,on_time:int},chart:list<array{label:string,occurrences:int,minutes:int}>,range:string}
     */
    public function snapshot(int $companyId, array $filters): array
    {
        $range = ($filters['range'] ?? null) === 'week' ? 'week' : 'day';
        $start = $range === 'week' ? today()->subDays(6) : today();
        $query = $this->base($companyId, $filters);
        /** @var LengthAwarePaginator<int,object> $rows */
        $rows = (clone $query)->orderByDesc('r.work_date')->orderByDesc('r.id')->paginate(25);
        $metricsQuery = DB::table('attendance_records')->where('company_id', $companyId)->whereDate('work_date', '>=', $start);
        $lateOccurrences = (clone $metricsQuery)->where('late_minutes', '>', 0)->count();
        $lateEmployees = (clone $metricsQuery)->where('late_minutes', '>', 0)->distinct()->count('employee_id');
        $lateMinutes = (int) (clone $metricsQuery)->sum('late_minutes');
        $onTime = (clone $metricsQuery)->where('late_minutes', 0)->where('attendance_status', 'present')->count();
        $chart = [];
        for ($offset = 0; $offset < ($range === 'week' ? 7 : 1); $offset++) {
            $date = $start->copy()->addDays($offset);
            $daily = DB::table('attendance_records')->where('company_id', $companyId)->whereDate('work_date', $date);
            $chart[] = ['label' => $date->format('d/m'), 'occurrences' => (clone $daily)->where('late_minutes', '>', 0)->count(), 'minutes' => (int) $daily->sum('late_minutes')];
        }

        return ['rows' => $rows, 'metrics' => ['late_occurrences' => $lateOccurrences, 'late_employees' => $lateEmployees, 'late_minutes' => $lateMinutes, 'on_time' => $onTime], 'chart' => $chart, 'range' => $range];
    }

    /** @param array<string,mixed> $filters */
    private function base(int $companyId, array $filters): Builder
    {
        $query = DB::table('attendance_records as r')->join('companies as c', 'c.id', '=', 'r.company_id')->join('employees as e', 'e.id', '=', 'r.employee_id')->leftJoin('branches as b', 'b.id', '=', 'r.branch_id')->leftJoin('employee_assignments as a', function (JoinClause $join): void {
            $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereDate('a.effective_from', '<=', today())->whereNull('a.effective_to');
        })->leftJoin('organization_units as o', 'o.id', '=', 'a.organization_unit_id')->leftJoin('job_positions as p', 'p.id', '=', 'a.job_position_id')->where('r.company_id', $companyId)->select(['r.*', 'c.name as company_name', 'b.name as branch_name', 'e.employee_code', 'e.full_name', 'o.name as organization_name', 'p.title as position_title']);
        foreach (['branch_id' => 'r.branch_id', 'organization_unit_id' => 'a.organization_unit_id'] as $key => $column) {
            $value = $filters[$key] ?? null;
            if (is_numeric($value) && (int) $value > 0) {
                $query->where($column, (int) $value);
            }
        }
        foreach (['approval_status', 'attendance_status'] as $key) {
            $value = $filters[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $query->where('r.'.$key, $value);
            }
        }
        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $query->where(fn (Builder $nested): Builder => $nested->where('e.full_name', 'like', '%'.$search.'%')->orWhere('e.employee_code', 'like', '%'.$search.'%'));
        }
        foreach (['start_date' => '>=', 'end_date' => '<='] as $key => $operator) {
            $value = $filters[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $query->whereDate('r.work_date', $operator, $value);
            }
        }

        return $query;
    }
}
