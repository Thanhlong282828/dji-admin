<?php

namespace Modules\Performance\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class PerformanceActionData
{
    public function __construct(public int $companyId, public int $actorId, public string $idempotencyKey, public ?int $cycleId = null, public ?int $employeeId = null, public ?int $goalId = null, public ?int $reviewId = null, public ?int $reviewerId = null, public ?string $name = null, public ?string $title = null, public ?string $description = null, public ?string $reason = null, public ?string $value = null, public ?string $rating = null, public ?CarbonImmutable $periodStart = null, public ?CarbonImmutable $periodEnd = null) {}
}
