<?php

namespace Modules\Performance\Domain\Services;

use App\Support\Compensation\CompensationReadService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Application\DTOs\WorkforceForecastViewModel;
use Modules\Performance\Application\DTOs\WorkforceScenarioLine;

final readonly class WorkforceForecastService
{
    public function __construct(private CompensationReadService $compensation) {}

    /** @param list<WorkforceScenarioLine> $scenarioLines */
    public function build(int $companyId, CarbonImmutable $forecastMonth, array $scenarioLines, bool $salaryAllowed, ?int $unitId = null): WorkforceForecastViewModel
    {
        $positions = $this->positions($companyId, $unitId);
        $positionMap = [];
        foreach ($positions as $position) {
            $positionMap[$position['position_id']] = $position;
        }

        $current = array_sum(array_column($positions, 'current_headcount'));
        $approved = array_sum(array_column($positions, 'approved_headcount'));
        $requisitions = $this->recruitment($companyId, array_keys($positionMap));
        $baseline = $salaryAllowed ? $this->currentCompensation($companyId, $forecastMonth, $unitId) : null;
        $emptyDelta = ['headcount' => 0, 'base_minor' => 0, 'kpi_minor' => 0, 'allowance_minor' => 0, 'target_gross_minor' => 0, 'annualized_target_gross_minor' => 0];
        $comparison = ['A' => $emptyDelta, 'B' => $emptyDelta];
        $headcountDeltas = ['A' => 0, 'B' => 0];
        $warnings = [];
        $references = [];
        foreach ($this->compensation->positionReferences($companyId) as $reference) {
            $references[(int) $reference['position_id']] = $reference;
        }

        foreach ($scenarioLines as $line) {
            if (! ForecastMath::applies($line->effectiveMonth, $forecastMonth) || ! isset($positionMap[$line->positionId])) {
                continue;
            }
            $sign = $line->action === 'remove' ? -1 : 1;
            if ($line->action === 'promote') {
                $sign = 0;
            }
            $scenarioName = $line->scenarioName === 'B' ? 'B' : 'A';
            $headcountDeltas[$scenarioName] += $sign * $line->quantity;
            if (! $salaryAllowed || ! isset($references[$line->positionId])) {
                continue;
            }
            $target = $references[$line->positionId];
            $source = $line->fromPositionId !== null ? ($references[$line->fromPositionId] ?? null) : null;
            foreach (['base_salary_minor' => 'base_minor', 'kpi_target_minor' => 'kpi_minor', 'fixed_allowance_minor' => 'allowance_minor', 'target_gross_minor' => 'target_gross_minor'] as $sourceKey => $deltaKey) {
                $unitDelta = (int) $target[$sourceKey];
                if ($line->action === 'remove') {
                    $unitDelta *= -1;
                } elseif ($line->action === 'promote') {
                    if ($source === null || (int) $target['career_level'] <= (int) $source['career_level']) {
                        $warnings[] = 'Một dòng promotion không có vị trí nguồn hợp lệ hoặc target level không cao hơn; dòng này không cộng chi phí.';
                        $unitDelta = 0;
                    } else {
                        $unitDelta -= (int) $source[$sourceKey];
                    }
                }
                $comparison[$scenarioName][$deltaKey] += $unitDelta * $line->quantity;
            }
        }
        foreach (['A', 'B'] as $scenarioName) {
            $comparison[$scenarioName]['headcount'] = $headcountDeltas[$scenarioName];
            $comparison[$scenarioName]['annualized_target_gross_minor'] = ForecastMath::annualize($comparison[$scenarioName]['target_gross_minor']);
        }
        $delta = $comparison['A'];

        $projection = [];
        foreach ($positions as $position) {
            $projection[] = $position + ['vacancy' => ForecastMath::vacancy($position['approved_headcount'], $position['current_headcount'])];
        }

        if ($positions === []) {
            $warnings[] = 'Không có Job Position hợp lệ trong phạm vi; forecast không thể phân bổ headcount.';
        }
        if ($salaryAllowed && ($baseline['employees_with_compensation'] ?? 0) < $current) {
            $warnings[] = 'Một phần Employee chưa có salary assignment hiệu lực; chi phí baseline loại trừ giá trị chưa biết.';
        }

        return new WorkforceForecastViewModel(
            ['current_headcount' => $current, 'approved_headcount' => $approved, 'vacancy' => ForecastMath::vacancy($approved, $current), 'open_requisition_vacancies' => $requisitions['open_requisition_vacancies'], 'candidate_pipeline' => $requisitions['candidate_pipeline'], 'candidate_to_vacancy_basis_points' => $requisitions['candidate_to_vacancy_basis_points']],
            $baseline,
            $delta,
            $comparison,
            $positions,
            $this->units($companyId),
            $projection,
            ['Approved headcount được giữ nguyên trong kỳ forecast.', 'Current headcount lấy từ primary assignment hiệu lực.', 'Compensation lấy từ salary assignment hiện tại; scenario dùng synthetic position reference v1.', 'Open requisitions được tính deterministic, không phải ML prediction.', 'Thuế, bảo hiểm và employer cost bị loại trừ khỏi forecast này.'],
            array_values(array_unique($warnings)),
            $salaryAllowed,
            $forecastMonth->format('Y-m'),
            count($scenarioLines),
        );
    }

    /** @return list<array{position_id:int,position_title:string,unit_id:int,unit_name:string,career_level:int,approved_headcount:int,current_headcount:int}> */
    private function positions(int $companyId, ?int $unitId): array
    {
        $current = DB::table('employee_assignments')->selectRaw('job_position_id, COUNT(DISTINCT employee_id) AS people')->where('assignment_type', 'primary')->whereDate('effective_from', '<=', today())->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->groupBy('job_position_id');

        $rows = DB::table('job_positions as p')->join('organization_units as u', 'u.id', '=', 'p.organization_unit_id')->leftJoinSub($current, 'ca', 'ca.job_position_id', '=', 'p.id')->where('p.company_id', $companyId)->when($unitId, fn (Builder $query, int $id): Builder => $query->where('p.organization_unit_id', $id))->whereBetween('p.career_level', [1, 6])->orderBy('u.name')->orderBy('p.career_level')->get(['p.id', 'p.title', 'p.organization_unit_id', 'u.name as unit_name', 'p.career_level', 'p.approved_headcount', DB::raw('COALESCE(ca.people,0) AS current_headcount')]);
        $result = [];
        foreach ($rows as $raw) {
            $row = (array) $raw;
            if (! is_numeric($row['id'] ?? null) || ! is_string($row['title'] ?? null) || ! is_numeric($row['organization_unit_id'] ?? null) || ! is_string($row['unit_name'] ?? null) || ! is_numeric($row['career_level'] ?? null) || ! is_numeric($row['approved_headcount'] ?? null) || ! is_numeric($row['current_headcount'] ?? null)) {
                continue;
            }
            $result[] = ['position_id' => (int) $row['id'], 'position_title' => $row['title'], 'unit_id' => (int) $row['organization_unit_id'], 'unit_name' => $row['unit_name'], 'career_level' => (int) $row['career_level'], 'approved_headcount' => (int) $row['approved_headcount'], 'current_headcount' => (int) $row['current_headcount']];
        }

        return $result;
    }

    /**
     * @param  list<int>  $positionIds
     * @return array{open_requisition_vacancies:int,candidate_pipeline:int,candidate_to_vacancy_basis_points:int}
     */
    private function recruitment(int $companyId, array $positionIds): array
    {
        if ($positionIds === []) {
            return ['open_requisition_vacancies' => 0, 'candidate_pipeline' => 0, 'candidate_to_vacancy_basis_points' => 0];
        }
        $open = DB::table('recruitment_requisitions')->whereIn('job_position_id', $positionIds)->where('status', 'open');
        $vacancies = max(0, (int) (clone $open)->sum(DB::raw('GREATEST(vacancies-filled_hires,0)')));
        $pipeline = DB::table('applications as a')->join('recruitment_requisitions as r', 'r.id', '=', 'a.recruitment_requisition_id')->whereIn('r.job_position_id', $positionIds)->whereNotIn('a.stage', ['hired', 'rejected'])->count();

        return ['open_requisition_vacancies' => $vacancies, 'candidate_pipeline' => $pipeline, 'candidate_to_vacancy_basis_points' => ForecastMath::ratioBasisPoints($pipeline, $vacancies)];
    }

    /** @return array{base_minor:int,kpi_minor:int,allowance_minor:int,target_gross_minor:int,currency:string,employees_with_compensation:int} */
    private function currentCompensation(int $companyId, CarbonImmutable $month, ?int $unitId): array
    {
        $query = DB::table('employee_salary_component_assignments as a')->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')->join('employees as e', 'e.id', '=', 'a.employee_id')->leftJoin('employee_assignments as ea', function (JoinClause $join) use ($month): void {
            $join->on('ea.employee_id', '=', 'e.id')->where('ea.assignment_type', 'primary')->whereDate('ea.effective_from', '<=', $month->endOfMonth())->where(function (JoinClause $query) use ($month): void {
                $query->whereNull('ea.effective_to')->orWhereDate('ea.effective_to', '>=', $month->startOfMonth());
            });
        })->where('a.company_id', $companyId)->where('a.status', 'active')->where('c.status', 'active')->whereDate('a.effective_from', '<=', $month->endOfMonth())->where(fn (Builder $q): Builder => $q->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $month->startOfMonth()))->when($unitId, fn (Builder $q, int $id): Builder => $q->where('ea.organization_unit_id', $id));
        $row = $query->selectRaw("SUM(CASE WHEN UPPER(c.code) LIKE '%BASE%' THEN COALESCE(a.amount_minor,0) ELSE 0 END) base_minor, SUM(CASE WHEN UPPER(c.code) LIKE '%KPI%' THEN COALESCE(a.amount_minor,0) ELSE 0 END) kpi_minor, SUM(CASE WHEN UPPER(c.code) LIKE '%ALLOW%' THEN COALESCE(a.amount_minor,0) ELSE 0 END) allowance_minor, SUM(COALESCE(a.amount_minor,0)) target_gross_minor, COUNT(DISTINCT a.employee_id) employees_with_compensation")->first();

        $values = (array) $row;

        return ['base_minor' => self::integer($values['base_minor'] ?? null), 'kpi_minor' => self::integer($values['kpi_minor'] ?? null), 'allowance_minor' => self::integer($values['allowance_minor'] ?? null), 'target_gross_minor' => self::integer($values['target_gross_minor'] ?? null), 'currency' => 'VND', 'employees_with_compensation' => self::integer($values['employees_with_compensation'] ?? null)];
    }

    /** @return list<array{id:int,name:string}> */
    private function units(int $companyId): array
    {
        $result = [];
        foreach (DB::table('organization_units')->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']) as $raw) {
            $row = (array) $raw;
            if (is_numeric($row['id'] ?? null) && is_string($row['name'] ?? null)) {
                $result[] = ['id' => (int) $row['id'], 'name' => $row['name']];
            }
        }

        return $result;
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
