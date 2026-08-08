<?php

namespace Modules\Performance\Domain\Events;

final readonly class GoalAssigned
{
    public function __construct(public int $companyId, public int $goalId) {}
}
