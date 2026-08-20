<?php

namespace Modules\Performance\Application\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AnalyticsFilter
{
    public function __construct(
        public int $companyId,
        public ?int $organizationUnitId,
        public ?int $jobPositionId,
        public ?int $careerLevel,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function fromRequest(Request $request, int $companyId): self
    {
        $request->validate([
            'organization_unit_id' => ['nullable', 'integer', 'min:1'],
            'job_position_id' => ['nullable', 'integer', 'min:1'],
            'career_level' => ['nullable', 'integer', 'between:1,6'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $toValue = $request->string('date_to')->toString();
        $fromValue = $request->string('date_from')->toString();
        $to = $toValue !== '' ? CarbonImmutable::parse($toValue)->startOfDay() : CarbonImmutable::today();
        $from = $fromValue !== '' ? CarbonImmutable::parse($fromValue)->startOfDay() : $to->subDays(29);
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['date_from' => 'Khoảng thời gian analytics không được vượt quá 366 ngày.']);
        }

        $unit = self::nullableInt($request->input('organization_unit_id'));
        $position = self::nullableInt($request->input('job_position_id'));
        $level = self::nullableInt($request->input('career_level'));
        if ($unit !== null && ! DB::table('organization_units')->where('company_id', $companyId)->where('id', $unit)->exists()) {
            throw ValidationException::withMessages(['organization_unit_id' => 'Đơn vị không thuộc công ty đang chọn.']);
        }
        if ($position !== null) {
            $query = DB::table('job_positions')->where('company_id', $companyId)->where('id', $position);
            if ($unit !== null) {
                $query->where('organization_unit_id', $unit);
            }
            if (! $query->exists()) {
                throw ValidationException::withMessages(['job_position_id' => 'Vị trí không thuộc công ty/đơn vị đang chọn.']);
            }
        }

        return new self($companyId, $unit, $position, $level, $from, $to);
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
