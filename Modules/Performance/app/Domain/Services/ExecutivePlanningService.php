<?php

namespace Modules\Performance\Domain\Services;

use App\Support\Compensation\CompensationReadService;
use App\Support\DataQuality\DataQualityAuditService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Application\DTOs\ExecutivePlanningViewModel;
use Modules\Performance\Application\DTOs\PlanningFilter;

final readonly class ExecutivePlanningService
{
    public function __construct(private CompensationReadService $compensation, private DataQualityAuditService $quality) {}

    public function build(PlanningFilter $filter, bool $salaryAllowed): ExecutivePlanningViewModel
    {
        $current = DB::table('employee_assignments')->selectRaw('job_position_id, COUNT(DISTINCT employee_id) people')
            ->where('assignment_type', 'primary')->whereDate('effective_from', '<=', $filter->period->endOfMonth())
            ->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $filter->period->startOfMonth()))->groupBy('job_position_id');

        $rows = DB::table('job_positions as p')->join('organization_units as u', 'u.id', '=', 'p.organization_unit_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'u.cost_center_id')->leftJoinSub($current, 'ca', 'ca.job_position_id', '=', 'p.id')
            ->where('p.company_id', $filter->companyId)->whereBetween('p.career_level', [1, 6])
            ->when($filter->costCenterId, fn (Builder $q, int $id): Builder => $q->where('u.cost_center_id', $id))
            ->when($filter->organizationUnitId, fn (Builder $q, int $id): Builder => $q->where('p.organization_unit_id', $id))
            ->when($filter->jobPositionId, fn (Builder $q, int $id): Builder => $q->where('p.id', $id))
            ->when($filter->careerLevel, fn (Builder $q, int $level): Builder => $q->where('p.career_level', $level))
            ->orderBy('cc.code')->orderBy('u.name')->orderBy('p.career_level')
            ->get(['p.id', 'p.title', 'p.organization_unit_id', 'p.career_level', 'p.approved_headcount', 'p.budgeted_cost', 'u.name as unit_name', 'u.cost_center_id', 'cc.code as cost_center_code', 'cc.name as cost_center_name', DB::raw('COALESCE(ca.people,0) current_headcount')])
            ->map(static fn (object $row): array => [
                'id' => self::intValue($row->id ?? null), 'title' => self::stringValue($row->title ?? null),
                'organization_unit_id' => self::intValue($row->organization_unit_id ?? null), 'career_level' => self::intValue($row->career_level ?? null),
                'approved_headcount' => self::intValue($row->approved_headcount ?? null), 'budgeted_cost' => self::floatValue($row->budgeted_cost ?? null),
                'unit_name' => self::stringValue($row->unit_name ?? null), 'cost_center_id' => self::nullableIntValue($row->cost_center_id ?? null),
                'cost_center_code' => self::stringValue($row->cost_center_code ?? null, 'UNASSIGNED'), 'cost_center_name' => self::stringValue($row->cost_center_name ?? null, 'Chưa gán Cost Center'),
                'current_headcount' => self::intValue($row->current_headcount ?? null),
            ])->values()->all();

        $references = [];
        foreach ($this->compensation->positionReferences($filter->companyId) as $reference) {
            $references[(int) $reference['position_id']] = $reference;
        }
        $positionIds = array_values(array_map(static fn (array $row): int => $row['id'], $rows));
        $recruitment = $this->recruitment($positionIds);
        $actualPayroll = $salaryAllowed ? $this->actualPayroll($filter) : null;
        $quality = $this->quality->snapshot();

        $positions = [];
        $units = [];
        $costCenters = [];
        $summary = ['approved_headcount' => 0, 'current_headcount' => 0, 'forecast_headcount' => 0, 'vacancy' => 0, 'hiring_demand' => 0, 'open_requisitions' => 0, 'candidates' => 0, 'offers' => 0, 'uncovered_demand' => 0, 'budget_payroll_minor' => 0, 'actual_payroll_minor' => $actualPayroll ?? 0, 'forecast_payroll_minor' => 0, 'variance_minor' => 0, 'variance_basis_points' => 0, 'annual_budget_minor' => 0, 'annual_forecast_minor' => 0, 'data_quality_score' => $quality['score']];
        foreach ($rows as $raw) {
            $id = $raw['id'];
            $approved = $raw['approved_headcount'];
            $filled = $raw['current_headcount'];
            $vacancy = PlanningMath::vacancy($approved, $filled);
            $reference = $references[$id] ?? null;
            $explicitBudget = (int) round($raw['budgeted_cost']);
            $budget = $explicitBudget > 0 ? $explicitBudget : ($reference !== null ? $approved * (int) $reference['target_gross_minor'] : 0);
            $forecast = $salaryAllowed && $reference !== null ? $filled * (int) $reference['target_gross_minor'] : 0;
            $hire = $recruitment[$id] ?? ['demand' => 0, 'requisitions' => 0, 'candidates' => 0, 'interviews' => 0, 'offers' => 0];
            $uncovered = max(0, $vacancy - $hire['demand']);
            $row = ['position_id' => $id, 'position_title' => $raw['title'], 'unit_id' => $raw['organization_unit_id'], 'unit_name' => $raw['unit_name'], 'cost_center_id' => $raw['cost_center_id'], 'cost_center' => $raw['cost_center_code'], 'career_level' => $raw['career_level'], 'approved_headcount' => $approved, 'current_headcount' => $filled, 'forecast_headcount' => $filled, 'vacancy' => $vacancy, 'budget_minor' => $budget, 'forecast_minor' => $forecast, 'variance_minor' => PlanningMath::variance($forecast, $budget), 'compensation_reference_minor' => $reference !== null ? (int) $reference['target_gross_minor'] : 0];
            $positions[] = $row;
            $unitKey = $raw['organization_unit_id'];
            $centerKey = $raw['cost_center_id'] ?? 0;
            $units[$unitKey] ??= ['label' => $raw['unit_name'], 'approved' => 0, 'current' => 0, 'forecast' => 0, 'vacancy' => 0, 'budget_minor' => 0, 'forecast_minor' => 0, 'variance_minor' => 0];
            $costCenters[$centerKey] ??= ['label' => $raw['cost_center_name'], 'code' => $raw['cost_center_code'], 'approved' => 0, 'current' => 0, 'vacancy' => 0, 'budget_minor' => 0, 'forecast_minor' => 0, 'variance_minor' => 0];
            $units[$unitKey]['approved'] += $approved;
            $units[$unitKey]['current'] += $filled;
            $units[$unitKey]['forecast'] += $filled;
            $units[$unitKey]['vacancy'] += $vacancy;
            $units[$unitKey]['budget_minor'] += $budget;
            $units[$unitKey]['forecast_minor'] += $forecast;
            $units[$unitKey]['variance_minor'] += PlanningMath::variance($forecast, $budget);
            $costCenters[$centerKey]['approved'] += $approved;
            $costCenters[$centerKey]['current'] += $filled;
            $costCenters[$centerKey]['vacancy'] += $vacancy;
            $costCenters[$centerKey]['budget_minor'] += $budget;
            $costCenters[$centerKey]['forecast_minor'] += $forecast;
            $costCenters[$centerKey]['variance_minor'] += PlanningMath::variance($forecast, $budget);
            foreach (['approved_headcount' => $approved, 'current_headcount' => $filled, 'forecast_headcount' => $filled, 'vacancy' => $vacancy, 'hiring_demand' => $hire['demand'], 'open_requisitions' => $hire['requisitions'], 'candidates' => $hire['candidates'], 'offers' => $hire['offers'], 'uncovered_demand' => $uncovered, 'budget_payroll_minor' => $budget, 'forecast_payroll_minor' => $forecast] as $key => $value) {
                $summary[$key] += $value;
            }
            $positions[count($positions) - 1]['hiring_demand'] = $hire['demand'];
            $positions[count($positions) - 1]['uncovered_demand'] = $uncovered;
        }
        $summary['variance_minor'] = PlanningMath::variance((int) $summary['forecast_payroll_minor'], (int) $summary['budget_payroll_minor']);
        $summary['variance_basis_points'] = PlanningMath::varianceBasisPoints((int) $summary['forecast_payroll_minor'], (int) $summary['budget_payroll_minor']);
        $summary['annual_budget_minor'] = PlanningMath::annualize((int) $summary['budget_payroll_minor']);
        $summary['annual_forecast_minor'] = PlanningMath::annualize((int) $summary['forecast_payroll_minor']);

        $warnings = [];
        if ($quality['failed'] > 0) {
            $warnings[] = 'Data Quality có finding blocking; số liệu Planning cần được đối soát tại module sở hữu.';
        }
        if ((int) $summary['uncovered_demand'] > 0) {
            $warnings[] = 'Một phần vacancy chưa được open requisition bao phủ.';
        }
        if ($salaryAllowed && (int) $summary['variance_minor'] > 0) {
            $warnings[] = 'Forecast target compensation đang vượt position budget trong phạm vi chọn.';
        }

        return new ExecutivePlanningViewModel($summary, $positions, array_values($units), array_values($costCenters), array_values(array_filter($positions, static fn (array $row): bool => $row['vacancy'] > 0)), $warnings, ['Budget Payroll dùng job_positions.budgeted_cost; nếu bằng 0 mới fallback Approved HC × Position Compensation Reference.', 'Actual Payroll dùng run gần nhất trong planning month với trạng thái đã calculated/reviewed/finalized.', 'Latest Forecast không lưu scenario: current filled positions × Position Compensation Reference.', 'Scenario 3F-26 vẫn request-scoped; cockpit không tạo Approved Plan giả.', 'Mọi tiền được chuẩn hóa thành integer minor units trong read model.'], ['cost_centers' => $this->options('cost_centers', $filter->companyId), 'units' => $this->options('organization_units', $filter->companyId), 'positions' => $this->positionOptions($filter->companyId)], $salaryAllowed, $filter->period->format('Y-m'));
    }

    /**
     * @param  list<int>  $positionIds
     * @return array<int,array{demand:int,requisitions:int,candidates:int,interviews:int,offers:int}>
     */
    private function recruitment(array $positionIds): array
    {
        if ($positionIds === []) {
            return [];
        }
        $demand = DB::table('recruitment_requisitions')->whereIn('job_position_id', $positionIds)->where('status', 'open')->groupBy('job_position_id')->selectRaw('job_position_id, SUM(GREATEST(vacancies-filled_hires,0)) demand, COUNT(*) requisitions');
        $pipeline = DB::table('recruitment_requisitions as r')->leftJoin('applications as a', 'a.recruitment_requisition_id', '=', 'r.id')->leftJoin('application_interviews as i', 'i.application_id', '=', 'a.id')->leftJoin('candidate_offers as o', 'o.application_id', '=', 'a.id')->whereIn('r.job_position_id', $positionIds)->where('r.status', 'open')->groupBy('r.job_position_id')->selectRaw('r.job_position_id, COUNT(DISTINCT a.candidate_id) candidates, COUNT(DISTINCT i.id) interviews, COUNT(DISTINCT o.id) offers');
        $rows = DB::query()->fromSub($demand, 'demand')->leftJoinSub($pipeline, 'pipeline', 'pipeline.job_position_id', '=', 'demand.job_position_id')->get(['demand.job_position_id', 'demand.demand', 'demand.requisitions', DB::raw('COALESCE(pipeline.candidates,0) candidates'), DB::raw('COALESCE(pipeline.interviews,0) interviews'), DB::raw('COALESCE(pipeline.offers,0) offers')]);
        $result = [];
        foreach ($rows as $row) {
            $result[self::intValue($row->job_position_id ?? null)] = ['demand' => self::intValue($row->demand ?? null), 'requisitions' => self::intValue($row->requisitions ?? null), 'candidates' => self::intValue($row->candidates ?? null), 'interviews' => self::intValue($row->interviews ?? null), 'offers' => self::intValue($row->offers ?? null)];
        }

        return $result;
    }

    private function actualPayroll(PlanningFilter $filter): int
    {
        return self::intValue(DB::table('payroll_runs')->where('company_id', $filter->companyId)->whereBetween('period_end', [$filter->period->startOfMonth(), $filter->period->endOfMonth()])->whereIn('status', ['calculated', 'submitted', 'approved', 'finalized', 'locked', 'paid'])->orderByDesc('calculation_version')->orderByDesc('id')->value('gross_total_minor'));
    }

    /** @return list<array{id:int,name:string}> */
    private function options(string $table, int $company): array
    {
        return array_values(DB::table($table)->where('company_id', $company)->orderBy('name')->get(['id', 'name'])->map(static fn (object $row): array => ['id' => self::intValue($row->id ?? null), 'name' => self::stringValue($row->name ?? null)])->all());
    }

    /** @return list<array{id:int,name:string,unit_id:int,level:int}> */
    private function positionOptions(int $company): array
    {
        return array_values(DB::table('job_positions')->where('company_id', $company)->orderBy('title')->get(['id', 'title', 'organization_unit_id', 'career_level'])->map(static fn (object $row): array => ['id' => self::intValue($row->id ?? null), 'name' => self::stringValue($row->title ?? null), 'unit_id' => self::intValue($row->organization_unit_id ?? null), 'level' => self::intValue($row->career_level ?? null)])->all());
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function nullableIntValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function stringValue(mixed $value, string $fallback = ''): string
    {
        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
