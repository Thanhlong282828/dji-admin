<?php

namespace Modules\Performance\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Application\DTOs\CareerDashboardViewModel;
use Modules\Performance\Domain\Repositories\CareerReadRepositoryInterface;

final class EloquentCareerReadRepository implements CareerReadRepositoryInterface
{
    public function dashboard(int $c): CareerDashboardViewModel
    {
        return new CareerDashboardViewModel($this->rows(DB::table('career_paths')->where('company_id', $c)->latest()->get()), $this->rows(DB::table('employee_career_plans as p')->join('employees as e', 'e.id', '=', 'p.employee_id')->join('career_paths as cp', 'cp.id', '=', 'p.career_path_id')->where('p.company_id', $c)->select(['p.*', 'e.employee_code', 'e.full_name', 'cp.name as path_name'])->latest('p.id')->get()), $this->rows(DB::table('succession_plans as s')->join('job_positions as j', 'j.id', '=', 's.position_id')->where('s.company_id', $c)->select(['s.*', 'j.code as position_code', 'j.title as position_title', 'j.is_critical'])->latest('s.id')->get()), $this->rows(DB::table('employees')->where('company_id', $c)->whereNull('deleted_at')->select(['id', 'employee_code', 'full_name'])->orderBy('employee_code')->get()), $this->rows(DB::table('job_positions')->where('company_id', $c)->select(['id', 'code', 'title', 'is_critical'])->orderBy('code')->get()), DB::table('succession_candidates as sc')->join('succession_plans as s', 's.id', '=', 'sc.succession_plan_id')->where('s.company_id', $c)->where('sc.readiness_level', 'ready_now')->count(), DB::table('job_positions')->where('company_id', $c)->where('is_critical', true)->count());
    }

    public function employee360(int $c, int $e): array
    {
        $employee = DB::table('employees')->where('company_id', $c)->where('id', $e)->select(['id', 'employee_code', 'full_name', 'work_email'])->first();
        if ($employee === null) {
            abort(404);
        }
        $assignment = DB::table('employee_assignments as a')->leftJoin('job_positions as j', 'j.id', '=', 'a.job_position_id')->where('a.employee_id', $e)->where(function (Builder $q): void {
            $q->whereNull('a.effective_to')->orWhere('a.effective_to', '>=', today());
        })->select(['j.code', 'j.title'])->orderByDesc('a.effective_from')->first();

        return ['employee' => $this->one($employee), 'position' => $assignment ? $this->one($assignment) : null, 'plans' => $this->rows(DB::table('employee_career_plans as p')->join('career_paths as cp', 'cp.id', '=', 'p.career_path_id')->where('p.company_id', $c)->where('p.employee_id', $e)->select(['p.*', 'cp.name as path_name'])->get()), 'competency_gap' => $this->rows(DB::table('employee_competencies as ec')->join('competencies as co', 'co.id', '=', 'ec.competency_id')->where('ec.company_id', $c)->where('ec.employee_id', $e)->select(['co.code', 'co.name', 'ec.level'])->get()), 'history' => $this->timeline($c, $e)];
    }

    public function timeline(int $c, int $e): array
    {
        return $this->rows(DB::table('career_history')->where('company_id', $c)->where('employee_id', $e)->orderByDesc('id')->get());
    }

    public function talentPool(int $c): array
    {
        return $this->rows(DB::table('succession_candidates as sc')->join('succession_plans as s', 's.id', '=', 'sc.succession_plan_id')->join('employees as e', 'e.id', '=', 'sc.employee_id')->join('job_positions as j', 'j.id', '=', 's.position_id')->where('s.company_id', $c)->select(['sc.*', 'e.employee_code', 'e.full_name', 'j.title as target_position'])->orderByDesc('sc.score')->get());
    }

    /**
     * @param  iterable<int, object>  $source
     * @return list<array<string, mixed>>
     */
    private function rows(iterable $source): array
    {
        $out = [];
        foreach ($source as $r) {
            $out[] = $this->one($r);
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function one(object $r): array
    {
        $out = [];
        foreach (get_object_vars($r) as $k => $v) {
            if (is_string($k)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
