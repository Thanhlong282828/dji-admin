<?php

namespace Modules\Employee\Application\DTOs;

use Carbon\CarbonImmutable;
use Modules\Employee\Domain\Enums\AssignmentType;

final readonly class EmployeeAssignmentData
{
    public function __construct(
        public int $organizationUnitId,
        public int $jobPositionId,
        public ?int $managerId,
        public AssignmentType $type,
        public string $allocationPercent,
        public ?string $workLocation,
        public CarbonImmutable $effectiveFrom,
        public ?CarbonImmutable $effectiveTo,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $organizationUnitId = self::integer($data, 'organization_unit_id');
        $jobPositionId = self::integer($data, 'job_position_id');
        $managerId = array_key_exists('manager_id', $data) && $data['manager_id'] !== null
            ? self::integer($data, 'manager_id')
            : null;
        $type = self::string($data, 'assignment_type');
        $allocation = self::numeric($data, 'allocation_percent');
        $from = self::string($data, 'effective_from');
        $to = array_key_exists('effective_to', $data) && is_string($data['effective_to']) && $data['effective_to'] !== ''
            ? $data['effective_to']
            : null;

        return new self(
            $organizationUnitId,
            $jobPositionId,
            $managerId,
            AssignmentType::from($type),
            number_format($allocation, 2, '.', ''),
            isset($data['work_location']) && is_string($data['work_location']) ? $data['work_location'] : null,
            CarbonImmutable::parse($from)->startOfDay(),
            $to !== null ? CarbonImmutable::parse($to)->startOfDay() : null,
        );
    }

    /** @return array{organization_unit_id:int,job_position_id:int,manager_id:int|null,assignment_type:string,allocation_percent:string,work_location:string|null,effective_from:string,effective_to:string|null} */
    public function toArray(): array
    {
        return [
            'organization_unit_id' => $this->organizationUnitId,
            'job_position_id' => $this->jobPositionId,
            'manager_id' => $this->managerId,
            'assignment_type' => $this->type->value,
            'allocation_percent' => $this->allocationPercent,
            'work_location' => $this->workLocation,
            'effective_from' => $this->effectiveFrom->toDateString(),
            'effective_to' => $this->effectiveTo?->toDateString(),
        ];
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /** @param array<string, mixed> $data */
    private static function numeric(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
