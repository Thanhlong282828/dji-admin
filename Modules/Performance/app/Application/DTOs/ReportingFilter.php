<?php

namespace Modules\Performance\Application\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReportingFilter
{
    public function __construct(
        public int $companyId,
        public string $periodType,
        public CarbonImmutable $period,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $costCenterId,
        public ?int $organizationUnitId,
        public ?int $jobPositionId,
        public ?int $careerLevel,
    ) {}

    public static function fromRequest(Request $request, int $companyId): self
    {
        $request->validate([
            'period_type' => ['nullable', 'in:monthly,quarterly,ytd'],
            'period' => ['nullable', 'date_format:Y-m'],
            'cost_center_id' => ['nullable', 'integer', 'min:1'],
            'organization_unit_id' => ['nullable', 'integer', 'min:1'],
            'job_position_id' => ['nullable', 'integer', 'min:1'],
            'career_level' => ['nullable', 'integer', 'between:1,6'],
        ]);
        $type = $request->string('period_type')->toString() ?: 'monthly';
        $period = CarbonImmutable::parse(($request->string('period')->toString() ?: now()->format('Y-m')).'-01')->startOfMonth();
        [$from, $to] = match ($type) {
            'quarterly' => [$period->startOfQuarter(), $period->endOfQuarter()],
            'ytd' => [$period->startOfYear(), $period->endOfMonth()],
            default => [$period, $period->endOfMonth()],
        };
        $costCenter = self::integer($request->input('cost_center_id'));
        $unit = self::integer($request->input('organization_unit_id'));
        $position = self::integer($request->input('job_position_id'));
        $level = self::integer($request->input('career_level'));
        if ($costCenter !== null && ! DB::table('cost_centers')->where('company_id', $companyId)->where('id', $costCenter)->exists()) {
            throw ValidationException::withMessages(['cost_center_id' => 'Cost Center không thuộc công ty đang chọn.']);
        }
        if ($unit !== null && ! DB::table('organization_units')->where('company_id', $companyId)->where('id', $unit)->when($costCenter, fn ($q, int $id) => $q->where('cost_center_id', $id))->exists()) {
            throw ValidationException::withMessages(['organization_unit_id' => 'Đơn vị không thuộc phạm vi công ty/Cost Center.']);
        }
        if ($position !== null && ! DB::table('job_positions')->where('company_id', $companyId)->where('id', $position)->when($unit, fn ($q, int $id) => $q->where('organization_unit_id', $id))->exists()) {
            throw ValidationException::withMessages(['job_position_id' => 'Vị trí không thuộc phạm vi công ty/đơn vị.']);
        }

        return new self($companyId, $type, $period, $from, $to, $costCenter, $unit, $position, $level);
    }

    public function analytics(): AnalyticsFilter
    {
        return new AnalyticsFilter($this->companyId, $this->organizationUnitId, $this->jobPositionId, $this->careerLevel, $this->from, $this->to);
    }

    public function execution(): PlanningExecutionFilter
    {
        return new PlanningExecutionFilter(new PlanningFilter($this->companyId, $this->period, $this->costCenterId, $this->organizationUnitId, $this->jobPositionId, $this->careerLevel), 0, 0);
    }

    private static function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
