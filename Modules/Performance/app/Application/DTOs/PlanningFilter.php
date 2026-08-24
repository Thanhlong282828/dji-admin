<?php

namespace Modules\Performance\Application\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PlanningFilter
{
    public function __construct(
        public int $companyId,
        public CarbonImmutable $period,
        public ?int $costCenterId,
        public ?int $organizationUnitId,
        public ?int $jobPositionId,
        public ?int $careerLevel,
    ) {}

    public static function fromRequest(Request $request, int $companyId): self
    {
        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
            'cost_center_id' => ['nullable', 'integer', 'min:1'],
            'organization_unit_id' => ['nullable', 'integer', 'min:1'],
            'job_position_id' => ['nullable', 'integer', 'min:1'],
            'career_level' => ['nullable', 'integer', 'between:1,6'],
        ]);
        $period = CarbonImmutable::parse(($request->string('period')->toString() ?: now()->format('Y-m')).'-01')->startOfMonth();
        $costCenter = self::integer($request->input('cost_center_id'));
        $unit = self::integer($request->input('organization_unit_id'));
        $position = self::integer($request->input('job_position_id'));
        $level = self::integer($request->input('career_level'));

        if ($costCenter !== null && ! DB::table('cost_centers')->where('company_id', $companyId)->where('id', $costCenter)->exists()) {
            throw ValidationException::withMessages(['cost_center_id' => 'Cost Center không thuộc công ty đang chọn.']);
        }
        if ($unit !== null) {
            $query = DB::table('organization_units')->where('company_id', $companyId)->where('id', $unit);
            if ($costCenter !== null) {
                $query->where('cost_center_id', $costCenter);
            }
            if (! $query->exists()) {
                throw ValidationException::withMessages(['organization_unit_id' => 'Đơn vị không thuộc phạm vi Cost Center/công ty.']);
            }
        }
        if ($position !== null) {
            $query = DB::table('job_positions')->where('company_id', $companyId)->where('id', $position);
            if ($unit !== null) {
                $query->where('organization_unit_id', $unit);
            }
            if (! $query->exists()) {
                throw ValidationException::withMessages(['job_position_id' => 'Vị trí không thuộc phạm vi công ty/đơn vị.']);
            }
        }

        return new self($companyId, $period, $costCenter, $unit, $position, $level);
    }

    private static function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
