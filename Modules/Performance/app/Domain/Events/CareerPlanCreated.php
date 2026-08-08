<?php

namespace Modules\Performance\Domain\Events;

final readonly class CareerPlanCreated
{
    public const NAME = 'CareerPlanCreated';

    public function __construct(public int $companyId, public int $planId, public ?int $employeeId) {}
}
