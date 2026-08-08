<?php

namespace Modules\Performance\Domain\Events;

final readonly class SuccessorNominated
{
    public const NAME = 'SuccessorNominated';

    public function __construct(public int $companyId, public int $planId, public int $employeeId) {}
}
