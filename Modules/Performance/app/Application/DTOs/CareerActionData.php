<?php

namespace Modules\Performance\Application\DTOs;

final readonly class CareerActionData
{
    /** @param list<array{position_id:int,required_level:int,expected_duration_months:int|null}> $steps */
    public function __construct(public int $companyId, public int $actorId, public string $idempotencyKey, public ?int $careerPathId = null, public ?int $careerPlanId = null, public ?int $successionPlanId = null, public ?int $candidateId = null, public ?int $employeeId = null, public ?int $positionId = null, public ?string $name = null, public ?string $description = null, public ?int $currentStep = null, public ?int $targetStep = null, public ?string $readinessLevel = null, public ?string $score = null, public ?string $reason = null, public array $steps = []) {}
}
