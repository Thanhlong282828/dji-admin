<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Performance\Application\DTOs\PlanningExecutionFilter;
use Modules\Performance\Application\DTOs\PlanningExecutionViewModel;
use Modules\Performance\Domain\Enums\ManagementAction;

final readonly class PlanningExecutionService
{
    public function __construct(private ExecutivePlanningService $planning) {}

    public function build(PlanningExecutionFilter $filter, bool $salaryAllowed): PlanningExecutionViewModel
    {
        $baseline = $this->planning->build($filter->planning, $salaryAllowed);
        $hires = $this->actualHires($filter, array_map(static fn (array $row): int => (int) $row['position_id'], $baseline->positions));
        $blocked = (int) $baseline->summary['data_quality_score'] < 100;
        $positions = [];
        $units = [];
        $actualHires = 0;

        foreach ($baseline->positions as $source) {
            $positionId = (int) $source['position_id'];
            $targetHeadcount = (int) $source['approved_headcount'];
            $actualHeadcount = (int) $source['current_headcount'];
            $targetHires = (int) $source['vacancy'];
            $positionHires = $hires[$positionId] ?? 0;
            $actualHires += $positionHires;
            $remainingHires = max(0, $targetHires - $positionHires);
            $headcountStatus = ExecutionVarianceMath::status($actualHeadcount, $targetHeadcount, $blocked)->value;
            $hiringStatus = ExecutionVarianceMath::status($positionHires, $targetHires, $blocked)->value;
            $positions[] = $source + [
                'target_headcount' => $targetHeadcount,
                'actual_headcount' => $actualHeadcount,
                'headcount_variance' => ExecutionVarianceMath::variance($actualHeadcount, $targetHeadcount),
                'headcount_variance_basis_points' => ExecutionVarianceMath::basisPoints($actualHeadcount, $targetHeadcount),
                'target_hires' => $targetHires,
                'actual_hires' => $positionHires,
                'remaining_hires' => $remainingHires,
                'headcount_status' => $headcountStatus,
                'hiring_status' => $hiringStatus,
            ];

            $unitId = (int) $source['unit_id'];
            $units[$unitId] ??= ['unit_id' => $unitId, 'label' => (string) $source['unit_name'], 'target_headcount' => 0, 'actual_headcount' => 0, 'target_hires' => 0, 'actual_hires' => 0, 'payroll_target_minor' => 0, 'payroll_forecast_minor' => 0];
            $units[$unitId]['target_headcount'] += $targetHeadcount;
            $units[$unitId]['actual_headcount'] += $actualHeadcount;
            $units[$unitId]['target_hires'] += $targetHires;
            $units[$unitId]['actual_hires'] += $positionHires;
            $units[$unitId]['payroll_target_minor'] += (int) $source['budget_minor'];
            $units[$unitId]['payroll_forecast_minor'] += (int) $source['forecast_minor'];
        }

        $unitRows = [];
        foreach ($units as $unit) {
            $unitRows[] = $unit + [
                'headcount_variance' => ExecutionVarianceMath::variance($unit['actual_headcount'], $unit['target_headcount']),
                'hiring_variance' => ExecutionVarianceMath::variance($unit['actual_hires'], $unit['target_hires']),
                'payroll_forecast_variance_minor' => ExecutionVarianceMath::variance($unit['payroll_forecast_minor'], $unit['payroll_target_minor']),
                'headcount_status' => ExecutionVarianceMath::status($unit['actual_headcount'], $unit['target_headcount'], $blocked)->value,
                'hiring_status' => ExecutionVarianceMath::status($unit['actual_hires'], $unit['target_hires'], $blocked)->value,
                'payroll_status' => ExecutionVarianceMath::status($unit['payroll_forecast_minor'], $unit['payroll_target_minor'], $blocked)->value,
            ];
        }

        $targetHeadcount = (int) $baseline->summary['approved_headcount'];
        $actualHeadcount = (int) $baseline->summary['current_headcount'];
        $targetHires = (int) $baseline->summary['vacancy'];
        $payrollTarget = (int) $baseline->summary['budget_payroll_minor'];
        $payrollActual = (int) $baseline->summary['actual_payroll_minor'];
        $currentForecastHeadcount = (int) $baseline->summary['forecast_headcount'];
        $currentForecastPayroll = (int) $baseline->summary['forecast_payroll_minor'];
        $reforecastHeadcount = max(0, $currentForecastHeadcount + $filter->headcountDelta);
        $reforecastPayroll = $salaryAllowed ? ExecutionVarianceMath::reforecast($currentForecastPayroll, $filter->payrollAdjustmentBasisPoints) : 0;
        $summary = [
            'headcount_target' => $targetHeadcount, 'headcount_actual' => $actualHeadcount,
            'headcount_variance' => ExecutionVarianceMath::variance($actualHeadcount, $targetHeadcount),
            'headcount_variance_basis_points' => ExecutionVarianceMath::basisPoints($actualHeadcount, $targetHeadcount),
            'headcount_status' => ExecutionVarianceMath::status($actualHeadcount, $targetHeadcount, $blocked)->value,
            'hiring_target' => $targetHires, 'hiring_actual' => $actualHires,
            'hiring_remaining' => max(0, $targetHires - $actualHires),
            'hiring_status' => ExecutionVarianceMath::status($actualHires, $targetHires, $blocked)->value,
            'payroll_target_minor' => $payrollTarget, 'payroll_actual_minor' => $payrollActual,
            'payroll_variance_minor' => ExecutionVarianceMath::variance($payrollActual, $payrollTarget),
            'payroll_variance_basis_points' => ExecutionVarianceMath::basisPoints($payrollActual, $payrollTarget),
            'payroll_status' => ExecutionVarianceMath::status($payrollActual, $payrollTarget, $blocked)->value,
            'current_forecast_headcount' => $currentForecastHeadcount, 'reforecast_headcount' => $reforecastHeadcount,
            'reforecast_headcount_delta' => $reforecastHeadcount - $currentForecastHeadcount,
            'current_forecast_payroll_minor' => $currentForecastPayroll, 'reforecast_payroll_minor' => $reforecastPayroll,
            'reforecast_payroll_delta_minor' => $reforecastPayroll - $currentForecastPayroll,
            'open_requisitions' => (int) $baseline->summary['open_requisitions'],
            'pipeline_candidates' => (int) $baseline->summary['candidates'],
            'pipeline_offers' => (int) $baseline->summary['offers'],
            'data_quality_score' => (int) $baseline->summary['data_quality_score'],
        ];

        return new PlanningExecutionViewModel(
            $summary, $positions, $unitRows, $this->actions($summary, $blocked),
            $blocked ? ['Data Quality đang blocking; execution metrics chỉ dùng để đối soát và không được auto-correct.'] : $baseline->warnings,
            ['Target HC dùng approved_headcount; Hiring Target dùng vacancy tại kỳ.', 'Payroll Target dùng position budget; Actual dùng eligible Payroll Run trong tháng.', 'Reforecast v2 là explicit request-scoped comparison và không ghi đè Forecast gốc.', 'Management Action là deterministic category, không tự thực thi và không phải AI.'],
            $baseline->options, $salaryAllowed, $baseline->period,
        );
    }

    /**
     * @param  list<int>  $positionIds
     * @return array<int,int>
     */
    private function actualHires(PlanningExecutionFilter $filter, array $positionIds): array
    {
        if ($positionIds === []) {
            return [];
        }
        $period = $filter->planning->period;
        $rows = DB::table('candidate_offers as o')
            ->where('o.company_id', $filter->planning->companyId)->whereNotNull('o.hired_employee_id')
            ->whereIn('o.job_position_id', $positionIds)
            ->whereBetween('o.hired_at', [$period->startOfMonth(), $period->endOfMonth()])
            ->groupBy('o.job_position_id')->selectRaw('o.job_position_id, COUNT(DISTINCT o.hired_employee_id) hires')->get();
        $result = [];
        foreach ($rows as $row) {
            $positionId = self::intValue($row->job_position_id ?? null);
            if ($positionId > 0) {
                $result[$positionId] = self::intValue($row->hires ?? null);
            }
        }

        return $result;
    }

    /**
     * @param  array<string,int|string>  $summary
     * @return list<array{category:string,label:string,detail:string}>
     */
    private function actions(array $summary, bool $blocked): array
    {
        if ($blocked) {
            return [['category' => ManagementAction::ReviewHeadcount->value, 'label' => 'Đối soát Data Quality', 'detail' => 'Sửa finding tại module sở hữu trước khi dùng execution variance.']];
        }
        $actions = [];
        if ((int) $summary['hiring_remaining'] > 0) {
            $actions[] = ['category' => ManagementAction::OpenRequisition->value, 'label' => 'Đánh giá nhu cầu mở requisition', 'detail' => 'Hiring Target còn thiếu coverage từ hire đã hoàn tất.'];
        }
        if ((int) $summary['payroll_variance_minor'] > 0) {
            $actions[] = ['category' => ManagementAction::ReviewBudget->value, 'label' => 'Rà soát Payroll budget', 'detail' => 'Actual Payroll đang cao hơn operating target.'];
        }
        if ((int) $summary['headcount_variance'] !== 0) {
            $actions[] = ['category' => ManagementAction::ReviewHeadcount->value, 'label' => 'Rà soát Headcount', 'detail' => 'Current Employee count chưa khớp monthly target.'];
        }

        return $actions !== [] ? $actions : [['category' => ManagementAction::NoAction->value, 'label' => 'Không cần hành động', 'detail' => 'Các execution target trong phạm vi đang đúng kế hoạch.']];
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
