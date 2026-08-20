<?php

namespace Modules\Employee\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class LifecycleTransitionData
{
    public function __construct(
        public int $companyId,
        public int $employeeId,
        public int $actorId,
        public string $idempotencyKey,
        public CarbonImmutable $effectiveAt,
        public ?string $reason = null,
        public ?int $currentAssignmentId = null,
        public ?int $organizationUnitId = null,
        public ?int $positionId = null,
        public ?int $managerId = null,
        public bool $promotion = false,
        public bool $demotion = false,
    ) {}
}
