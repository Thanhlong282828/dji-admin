<?php

namespace Modules\Attendance\Application\Queries;

final readonly class GetWorkforceCsvExportQuery
{
    public function __construct(
        public string $type,
        public int $companyId,
        public int $actorId,
        public ?int $employeeId = null,
        public ?int $shiftId = null,
        public ?int $policyId = null,
        public ?int $snapshotId = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $status = null,
        public ?string $approvalStatus = null,
        public ?string $calculationStatus = null,
        public ?bool $locked = null,
    ) {}
}
