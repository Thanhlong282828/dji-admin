<?php

namespace Modules\Performance\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Performance\Application\DTOs\EmployeePerformanceViewModel;
use Modules\Performance\Domain\Repositories\PerformanceReadRepositoryInterface;
use Modules\Performance\Domain\Services\CompetencyService;

final readonly class EloquentPerformanceReadRepository implements PerformanceReadRepositoryInterface
{
    public function __construct(private CompetencyService $competencies) {}

    public function dashboard(int $companyId): array
    {
        $cycles = $this->rows(DB::table('performance_cycles')->where('company_id', $companyId)->latest('period_start')->limit(20)->get());
        foreach ($cycles as &$cycle) {
            $cycle['status'] = $cycle['talent_status'] ?? $cycle['status'];
        }
        $employees = $this->rows(DB::table('employees')->where('company_id', $companyId)->whereNull('deleted_at')->orderBy('employee_code')->select(['id', 'employee_code', 'full_name'])->get());

        return ['cycles' => $cycles, 'employees' => $employees, 'stats' => ['cycles' => count($cycles), 'goals' => DB::table('employee_goals')->where('company_id', $companyId)->count(), 'reviews' => DB::table('performance_reviews')->where('company_id', $companyId)->count(), 'approved' => DB::table('performance_reviews')->where('company_id', $companyId)->where('status', 'approved')->count()]];
    }

    public function employee360(int $companyId, int $employeeId): EmployeePerformanceViewModel
    {
        $employee = DB::table('employees')->where('company_id', $companyId)->where('id', $employeeId)->select(['id', 'employee_code', 'full_name', 'work_email'])->first();
        if ($employee === null) {
            abort(404);
        } $goals = $this->rows(DB::table('employee_goals')->where('company_id', $companyId)->where('employee_id', $employeeId)->latest()->get());
        $reviews = $this->rows(DB::table('performance_reviews')->where('company_id', $companyId)->where('employee_id', $employeeId)->latest()->get());

        return new EmployeePerformanceViewModel($this->object($employee), $goals, $reviews, $this->competencies->profile($companyId, $employeeId), $this->history($companyId, $employeeId));
    }

    public function history(int $companyId, int $employeeId): array
    {
        return $this->rows(DB::table('performance_review_history as h')->join('performance_reviews as r', 'r.id', '=', 'h.review_id')->where('h.company_id', $companyId)->where('r.employee_id', $employeeId)->orderByDesc('h.id')->select('h.*')->get());
    }

    public function goalTimeline(int $companyId, int $goalId): array
    {
        return $this->rows(DB::table('goal_progress_history as h')->join('employee_goals as g', 'g.id', '=', 'h.goal_id')->where('g.company_id', $companyId)->where('g.id', $goalId)->orderByDesc('h.id')->select('h.*')->get());
    }

    public function competencyProfile(int $companyId, int $employeeId): array
    {
        return $this->competencies->profile($companyId, $employeeId);
    }

    /**
     * @param  iterable<int, object>  $source
     * @return list<array<string, mixed>>
     */
    private function rows(iterable $source): array
    {
        $rows = [];
        foreach ($source as $r) {
            $rows[] = $this->object($r);
        }

        return $rows;
    }

    /** @return array<string,mixed> */
    private function object(object $r): array
    {
        $result = [];
        foreach (get_object_vars($r) as $k => $v) {
            if (is_string($k)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }
}
