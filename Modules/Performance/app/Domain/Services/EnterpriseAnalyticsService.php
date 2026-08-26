<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Application\DTOs\AnalyticsFilter;

final readonly class EnterpriseAnalyticsService
{
    /** @return array<string, mixed> */
    public function build(AnalyticsFilter $filter, bool $salaryAllowed): array
    {
        $assignment = $this->currentAssignment();
        $employees = DB::table('employees as e')->leftJoinSub($assignment, 'ca', 'ca.employee_id', '=', 'e.id')
            ->leftJoin('organization_units as ou', 'ou.id', '=', 'ca.organization_unit_id')
            ->leftJoin('job_positions as jp', 'jp.id', '=', 'ca.job_position_id')
            ->where('e.company_id', $filter->companyId)->whereNull('e.deleted_at');
        $this->applyWorkforceFilter($employees, $filter);

        $total = (clone $employees)->count('e.id');
        $active = (clone $employees)->where('e.employment_status', 'active')->count('e.id');
        $probation = (clone $employees)->where('e.employment_status', 'probation')->count('e.id');
        $inactive = (clone $employees)->whereIn('e.employment_status', ['suspended', 'leave', 'terminated', 'alumni'])->count('e.id');
        $positions = DB::table('job_positions')->where('company_id', $filter->companyId)
            ->when($filter->organizationUnitId, fn (Builder $q, int $id): Builder => $q->where('organization_unit_id', $id))
            ->when($filter->jobPositionId, fn (Builder $q, int $id): Builder => $q->where('id', $id))
            ->when($filter->careerLevel, fn (Builder $q, int $level): Builder => $q->where('career_level', $level));
        $approved = (int) (clone $positions)->sum('approved_headcount');

        return [
            'executive' => [
                'total_employees' => $total,
                'active_employees' => $active,
                'probation' => $probation,
                'inactive' => $inactive,
                'departments' => DB::table('organization_units')->where('company_id', $filter->companyId)->when($filter->organizationUnitId, fn (Builder $q, int $id): Builder => $q->where('id', $id))->count(),
                'open_positions' => max(0, $approved - $active),
                'headcount_vacancy' => max(0, $approved - $total),
            ],
            'workforce' => [
                'by_department' => $this->group($employees, 'ou.name', 'Chưa gán'),
                'by_position' => $this->group($employees, 'jp.title', 'Chưa gán'),
                'by_level' => $this->group($employees, 'jp.career_level', 'Chưa gán'),
                'by_gender' => $this->group($employees, 'e.gender', 'Chưa cập nhật'),
                'by_status' => $this->group($employees, 'e.employment_status', 'unknown'),
                'age_bands' => $this->ageBands($employees),
                'joiners' => $this->monthlyEmployees($employees, 'e.join_date'),
                'leavers' => $this->monthlyEmployees($employees, 'e.termination_date'),
                'manager_span' => $this->managerSpan($employees),
            ],
            'attendance' => $this->attendance($filter),
            'leave' => $this->leave($filter, $assignment),
            'recruitment' => $this->recruitment($filter),
            'organization' => $this->organization($filter, $assignment),
            'payroll' => $salaryAllowed ? $this->payroll($filter) : null,
            'compensation' => $salaryAllowed ? $this->compensation($filter, $assignment) : null,
            'workflow' => $this->workflow($filter),
            'iam' => $this->iam($filter),
            'quality' => $this->tenantQuality($filter->companyId),
            'salary_allowed' => $salaryAllowed,
            'filter' => ['from' => $filter->from->toDateString(), 'to' => $filter->to->toDateString()],
        ];
    }

    private function currentAssignment(): Builder
    {
        return DB::table('employee_assignments')->select(['employee_id', 'organization_unit_id', 'job_position_id', 'manager_id'])
            ->where('assignment_type', 'primary')->whereDate('effective_from', '<=', today())
            ->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()));
    }

    private function applyWorkforceFilter(Builder $query, AnalyticsFilter $filter): void
    {
        $query->when($filter->organizationUnitId, fn (Builder $q, int $id): Builder => $q->where('ca.organization_unit_id', $id))
            ->when($filter->jobPositionId, fn (Builder $q, int $id): Builder => $q->where('ca.job_position_id', $id))
            ->when($filter->careerLevel, fn (Builder $q, int $level): Builder => $q->where('jp.career_level', $level));
    }

    /**
     * @param  literal-string  $column
     * @return list<array{label:string,value:int}>
     */
    private function group(Builder $source, string $column, string $fallback): array
    {
        return array_values((clone $source)->selectRaw("COALESCE(CAST($column AS CHAR), ?) AS label, COUNT(e.id) AS aggregate", [$fallback])->groupBy($column)->orderByDesc('aggregate')->limit(20)->get()
            ->map(fn (object $row): array => ['label' => self::stringValue($row->label ?? null), 'value' => self::intValue($row->aggregate ?? null)])->all());
    }

    /** @return list<array{label:string,value:int}> */
    private function ageBands(Builder $source): array
    {
        $expression = "CASE WHEN e.date_of_birth IS NULL THEN 'Chưa cập nhật' WHEN TIMESTAMPDIFF(YEAR,e.date_of_birth,CURDATE()) < 25 THEN '<25' WHEN TIMESTAMPDIFF(YEAR,e.date_of_birth,CURDATE()) < 35 THEN '25–34' WHEN TIMESTAMPDIFF(YEAR,e.date_of_birth,CURDATE()) < 45 THEN '35–44' ELSE '45+' END";

        return array_values((clone $source)->selectRaw("$expression AS label, COUNT(e.id) AS aggregate")->groupByRaw($expression)->get()->map(fn (object $row): array => ['label' => self::stringValue($row->label ?? null), 'value' => self::intValue($row->aggregate ?? null)])->all());
    }

    /**
     * @param  literal-string  $column
     * @return list<array{label:string,value:int}>
     */
    private function monthlyEmployees(Builder $source, string $column): array
    {
        return array_values((clone $source)->whereNotNull($column)->selectRaw("DATE_FORMAT($column, '%Y-%m') AS label, COUNT(e.id) AS aggregate")->groupByRaw("DATE_FORMAT($column, '%Y-%m')")->orderBy('label')->limit(12)->get()->map(fn (object $row): array => ['label' => self::stringValue($row->label ?? null), 'value' => self::intValue($row->aggregate ?? null)])->all());
    }

    /** @return array{managers:int,average:float,maximum:int} */
    private function managerSpan(Builder $source): array
    {
        $rows = (clone $source)->whereNotNull('e.manager_id')->selectRaw('e.manager_id, COUNT(e.id) AS aggregate')->groupBy('e.manager_id')->pluck('aggregate');

        return ['managers' => $rows->count(), 'average' => round(self::floatValue($rows->avg()), 2), 'maximum' => self::intValue($rows->max())];
    }

    /** @return array<string, mixed> */
    private function attendance(AnalyticsFilter $filter): array
    {
        $q = DB::table('attendance_records as ar')->join('employees as e', 'e.id', '=', 'ar.employee_id')->leftJoinSub($this->currentAssignment(), 'ca', 'ca.employee_id', '=', 'e.id')->leftJoin('job_positions as jp', 'jp.id', '=', 'ca.job_position_id')->where('ar.company_id', $filter->companyId)->whereBetween('ar.work_date', [$filter->from->toDateString(), $filter->to->toDateString()]);
        $this->applyWorkforceFilter($q, $filter);
        $total = (clone $q)->count();
        $present = (clone $q)->whereIn('ar.attendance_status', ['present', 'late'])->count();
        $late = (clone $q)->where('ar.late_minutes', '>', 0)->count();

        return ['records' => $total, 'attendance_rate' => self::percentage($present, $total), 'late_rate' => self::percentage($late, $total), 'late_count' => $late, 'late_minutes' => (int) (clone $q)->sum('ar.late_minutes'), 'overtime_hours' => round(((int) (clone $q)->sum('ar.overtime_minutes')) / 60, 2), 'absence' => (clone $q)->where('ar.attendance_status', 'absent')->count(), 'daily' => $this->datedGroup($q, 'ar.work_date')];
    }

    /** @return array<string, mixed> */
    private function leave(AnalyticsFilter $filter, Builder $assignment): array
    {
        $q = DB::table('leave_requests as lr')->join('employees as e', 'e.id', '=', 'lr.employee_id')->leftJoinSub($assignment, 'ca', 'ca.employee_id', '=', 'e.id')->leftJoin('job_positions as jp', 'jp.id', '=', 'ca.job_position_id')->leftJoin('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')->where('lr.company_id', $filter->companyId)->whereBetween('lr.starts_at', [$filter->from->toDateString(), $filter->to->toDateString()]);
        $this->applyWorkforceFilter($q, $filter);
        $total = (clone $q)->count();

        return ['requests' => $total, 'approved' => (clone $q)->where('lr.status', 'approved')->count(), 'rejected' => (clone $q)->where('lr.status', 'rejected')->count(), 'pending' => (clone $q)->whereIn('lr.status', ['pending', 'submitted'])->count(), 'paid' => (clone $q)->where('lt.is_paid', true)->count(), 'unpaid' => (clone $q)->where('lt.is_paid', false)->count(), 'rate' => self::percentage($total, max(1, DB::table('employees')->where('company_id', $filter->companyId)->whereNull('deleted_at')->count())), 'monthly' => $this->datedGroup($q, 'lr.starts_at')];
    }

    /** @return array<string, int> */
    private function recruitment(AnalyticsFilter $filter): array
    {
        $positions = DB::table('job_positions')->where('company_id', $filter->companyId)->select('id');
        $from = $filter->from->startOfDay();
        $to = $filter->to->endOfDay();

        return [
            'open_requisitions' => DB::table('recruitment_requisitions')->whereIn('job_position_id', $positions)->where('status', 'open')->whereBetween('created_at', [$from, $to])->count(),
            'candidates' => DB::table('candidates')->where('company_id', $filter->companyId)->whereBetween('created_at', [$from, $to])->count(),
            'applications' => DB::table('applications as a')->join('candidates as c', 'c.id', '=', 'a.candidate_id')->where('c.company_id', $filter->companyId)->whereBetween('a.created_at', [$from, $to])->count(),
            'interviews' => DB::table('application_interviews')->where('company_id', $filter->companyId)->whereBetween('created_at', [$from, $to])->count(),
            'offers' => DB::table('candidate_offers')->where('company_id', $filter->companyId)->whereBetween('created_at', [$from, $to])->count(),
            'accepted_offers' => DB::table('candidate_offers')->where('company_id', $filter->companyId)->whereBetween('accepted_at', [$from, $to])->count(),
        ];
    }

    /** @return list<array{label:string,value:int,approved:int,vacancy:int,budget_minor:int}> */
    private function organization(AnalyticsFilter $filter, Builder $assignment): array
    {
        $headcount = DB::table('job_positions')->selectRaw('organization_unit_id, SUM(approved_headcount) AS approved, SUM(budgeted_cost) AS budget')->where('company_id', $filter->companyId)->groupBy('organization_unit_id');
        $people = DB::query()->fromSub($assignment, 'current_assignments')->selectRaw('organization_unit_id, COUNT(DISTINCT employee_id) AS people')->groupBy('organization_unit_id');

        return array_values(DB::table('organization_units as ou')->leftJoinSub($headcount, 'hc', 'hc.organization_unit_id', '=', 'ou.id')->leftJoinSub($people, 'pc', 'pc.organization_unit_id', '=', 'ou.id')->where('ou.company_id', $filter->companyId)->when($filter->organizationUnitId, fn (Builder $q, int $id): Builder => $q->where('ou.id', $id))->selectRaw('ou.name AS label, COALESCE(pc.people,0) AS people, COALESCE(hc.approved,0) AS approved, COALESCE(hc.budget,0) AS budget')->get()->map(function (object $row): array {
            $people = self::intValue($row->people ?? null);
            $approved = self::intValue($row->approved ?? null);

            return ['label' => self::stringValue($row->label ?? null), 'value' => $people, 'approved' => $approved, 'vacancy' => max(0, $approved - $people), 'budget_minor' => self::intValue($row->budget ?? null)];
        })->all());
    }

    /** @return array<string, mixed> */
    private function payroll(AnalyticsFilter $filter): array
    {
        $q = DB::table('payroll_runs')->where('company_id', $filter->companyId)->whereBetween('period_end', [$filter->from->toDateString(), $filter->to->toDateString()])->whereIn('status', ['calculated', 'submitted', 'approved', 'finalized', 'locked', 'paid']);
        $items = DB::table('payroll_items as pi')->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')->where('pi.company_id', $filter->companyId)->whereBetween('pr.period_end', [$filter->from->toDateString(), $filter->to->toDateString()])->whereIn('pr.status', ['calculated', 'submitted', 'approved', 'finalized', 'locked', 'paid']);

        return ['runs' => (clone $q)->count(), 'gross_minor' => (int) (clone $q)->sum('gross_total_minor'), 'net_minor' => (int) (clone $q)->sum('net_total_minor'), 'deduction_minor' => (int) (clone $q)->sum('deduction_total_minor'), 'tax_minor' => (int) (clone $q)->sum('personal_income_tax_total_minor'), 'insurance_minor' => (int) (clone $q)->sum('insurance_employee_total_minor'), 'currency' => 'VND', 'by_period' => array_values((clone $q)->selectRaw("DATE_FORMAT(period_end,'%Y-%m') AS label, SUM(gross_total_minor) AS gross_minor, SUM(net_total_minor) AS net_minor")->groupByRaw("DATE_FORMAT(period_end,'%Y-%m')")->orderBy('label')->get()->map(fn (object $row): array => ['label' => self::stringValue($row->label ?? null), 'gross_minor' => self::intValue($row->gross_minor ?? null), 'net_minor' => self::intValue($row->net_minor ?? null)])->all()), 'by_department' => $this->payrollGroup($items, 'pi.organization_unit_snapshot'), 'by_position' => $this->payrollGroup($items, 'pi.job_position_snapshot')];
    }

    /**
     * @param  'pi.organization_unit_snapshot'|'pi.job_position_snapshot'  $column
     * @return list<array{label:string,gross_minor:int,net_minor:int}>
     */
    private function payrollGroup(Builder $source, string $column): array
    {
        $query = match ($column) {
            'pi.organization_unit_snapshot' => (clone $source)->selectRaw("COALESCE(pi.organization_unit_snapshot,'Chưa gán') AS label, SUM(pi.gross_amount_minor) AS gross_minor, SUM(pi.net_amount_minor) AS net_minor")->groupBy('pi.organization_unit_snapshot'),
            'pi.job_position_snapshot' => (clone $source)->selectRaw("COALESCE(pi.job_position_snapshot,'Chưa gán') AS label, SUM(pi.gross_amount_minor) AS gross_minor, SUM(pi.net_amount_minor) AS net_minor")->groupBy('pi.job_position_snapshot'),
        };

        return array_values($query->orderByDesc('gross_minor')->limit(20)->get()->map(fn (object $row): array => ['label' => self::stringValue($row->label ?? null), 'gross_minor' => self::intValue($row->gross_minor ?? null), 'net_minor' => self::intValue($row->net_minor ?? null)])->all());
    }

    /** @return array{by_department:list<array<string,int|string>>,by_level:list<array<string,int|string>>,by_position:list<array<string,int|string>>} */
    private function compensation(AnalyticsFilter $filter, Builder $assignment): array
    {
        return [
            'by_department' => $this->compensationGroup($filter, clone $assignment, 'ou.name'),
            'by_level' => $this->compensationGroup($filter, clone $assignment, 'jp.career_level'),
            'by_position' => $this->compensationGroup($filter, clone $assignment, 'jp.title'),
        ];
    }

    /**
     * @param  'ou.name'|'jp.career_level'|'jp.title'  $group
     * @return list<array<string, int|string>>
     */
    private function compensationGroup(AnalyticsFilter $filter, Builder $assignment, string $group): array
    {
        $label = match ($group) {
            'ou.name' => "COALESCE(ou.name,'Chưa gán')",
            'jp.career_level' => "COALESCE(CAST(jp.career_level AS CHAR),'Chưa gán')",
            'jp.title' => "COALESCE(jp.title,'Chưa gán')",
        };

        return array_values(DB::table('employee_salary_component_assignments as sa')->join('salary_components as sc', 'sc.id', '=', 'sa.salary_component_id')->join('employees as e', 'e.id', '=', 'sa.employee_id')->leftJoinSub($assignment, 'ca', 'ca.employee_id', '=', 'e.id')->leftJoin('organization_units as ou', 'ou.id', '=', 'ca.organization_unit_id')->leftJoin('job_positions as jp', 'jp.id', '=', 'ca.job_position_id')->where('sa.company_id', $filter->companyId)->where('sa.status', 'active')->where('sc.status', 'active')->whereDate('sa.effective_from', '<=', $filter->to)->where(fn (Builder $q): Builder => $q->whereNull('sa.effective_to')->orWhereDate('sa.effective_to', '>=', $filter->from))->when($filter->organizationUnitId, fn (Builder $q, int $id): Builder => $q->where('ca.organization_unit_id', $id))->when($filter->jobPositionId, fn (Builder $q, int $id): Builder => $q->where('ca.job_position_id', $id))->when($filter->careerLevel, fn (Builder $q, int $level): Builder => $q->where('jp.career_level', $level))->groupBy($group)->selectRaw("$label AS label, COUNT(DISTINCT e.id) AS aggregate, CAST(SUM(CASE WHEN sc.system_key='base_salary' THEN sa.amount_minor ELSE 0 END)/NULLIF(COUNT(DISTINCT CASE WHEN sc.system_key='base_salary' THEN e.id END),0) AS SIGNED) AS base_minor, CAST(SUM(CASE WHEN sc.system_key IN ('kpi','kpi_target') THEN sa.amount_minor ELSE 0 END)/NULLIF(COUNT(DISTINCT CASE WHEN sc.system_key IN ('kpi','kpi_target') THEN e.id END),0) AS SIGNED) AS kpi_minor, CAST(SUM(CASE WHEN sc.category='allowance' THEN sa.amount_minor ELSE 0 END)/NULLIF(COUNT(DISTINCT CASE WHEN sc.category='allowance' THEN e.id END),0) AS SIGNED) AS allowance_minor")->get()->map(function (object $row): array {
            $base = self::intValue($row->base_minor ?? null);
            $kpi = self::intValue($row->kpi_minor ?? null);
            $allowance = self::intValue($row->allowance_minor ?? null);

            return ['label' => self::stringValue($row->label ?? null), 'value' => self::intValue($row->aggregate ?? null), 'base_minor' => $base, 'kpi_minor' => $kpi, 'allowance_minor' => $allowance, 'target_gross_minor' => $base + $kpi + $allowance];
        })->all());
    }

    /** @return array<string, int|float> */
    private function workflow(AnalyticsFilter $filter): array
    {
        $q = DB::table('workflow_instances')->where('company_id', $filter->companyId)->whereBetween('created_at', [$filter->from->startOfDay(), $filter->to->endOfDay()]);
        $completed = (clone $q)->where('status', 'approved')->count();

        return ['open' => (clone $q)->where('status', 'pending')->count(), 'completed' => $completed, 'rejected' => (clone $q)->where('status', 'rejected')->count(), 'pending_tasks' => DB::table('workflow_tasks')->where('company_id', $filter->companyId)->whereIn('status', ['pending', 'claimed'])->count(), 'average_completion_hours' => 0.0];
    }

    /** @return array<string, int> */
    private function iam(AnalyticsFilter $filter): array
    {
        return ['requests' => DB::table('iam_access_requests')->where('company_id', $filter->companyId)->count(), 'approved' => DB::table('iam_access_requests')->where('company_id', $filter->companyId)->where('status', 'approved')->count(), 'denied' => DB::table('iam_access_requests')->where('company_id', $filter->companyId)->whereIn('status', ['denied', 'rejected'])->count(), 'pending_reviews' => DB::table('iam_access_reviews')->where('company_id', $filter->companyId)->whereIn('status', ['pending', 'open'])->count(), 'sod_findings' => DB::table('iam_sod_violations')->where('company_id', $filter->companyId)->count()];
    }

    /**
     * @param  literal-string  $column
     * @return list<array{label:string,value:int}>
     */
    private function datedGroup(Builder $source, string $column): array
    {
        return array_values((clone $source)->selectRaw("DATE($column) AS label, COUNT(*) AS aggregate")->groupByRaw("DATE($column)")->orderBy('label')->get()->map(fn (object $row): array => ['label' => self::stringValue($row->label ?? null), 'value' => self::intValue($row->aggregate ?? null)])->all());
    }

    public static function percentage(int $part, int $total): float
    {
        return $total === 0 ? 0.0 : round(($part / $total) * 100, 2);
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    /** @return array{rules:int,failed:int,score:int} */
    private function tenantQuality(int $companyId): array
    {
        $duplicateCodes = DB::query()->fromSub(DB::table('employees')->select('employee_code')->where('company_id', $companyId)->whereNull('deleted_at')->groupBy('employee_code')->havingRaw('COUNT(*) > 1'), 'duplicates')->count();
        $crossTenant = DB::table('employee_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('organization_units as ou', 'ou.id', '=', 'a.organization_unit_id')->where('e.company_id', $companyId)->whereColumn('e.company_id', '<>', 'ou.company_id')->count();
        $invalidLevels = DB::table('job_positions')->where('company_id', $companyId)->where(fn (Builder $q): Builder => $q->whereNull('career_level')->orWhere('career_level', '<', 1)->orWhere('career_level', '>', 6))->count();
        $attendanceOrphans = DB::table('attendance_records as ar')->leftJoin('employees as e', 'e.id', '=', 'ar.employee_id')->where('ar.company_id', $companyId)->where(fn (Builder $q): Builder => $q->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'ar.company_id'))->count();
        $payrollOrphans = DB::table('payroll_items as pi')->leftJoin('employees as e', 'e.id', '=', 'pi.employee_id')->where('pi.company_id', $companyId)->where(fn (Builder $q): Builder => $q->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'pi.company_id'))->count();
        $failed = count(array_filter([$duplicateCodes, $crossTenant, $invalidLevels, $attendanceOrphans, $payrollOrphans], static fn (int $count): bool => $count > 0));

        return ['rules' => 5, 'failed' => $failed, 'score' => max(0, 100 - ($failed * 20))];
    }
}
