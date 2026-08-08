<?php

namespace Modules\Performance\Domain\Events;

final readonly class CareerProgressUpdated
{
    public const NAME = 'CareerProgressUpdated';

    public function __construct(public int $companyId, public int $planId, public int $employeeId) {}
}
