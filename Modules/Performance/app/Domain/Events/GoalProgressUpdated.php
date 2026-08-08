<?php

namespace Modules\Performance\Domain\Events;

final readonly class GoalProgressUpdated
{
    public function __construct(public int $companyId, public int $goalId) {}
}
