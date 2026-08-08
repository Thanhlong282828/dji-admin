<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Enums\EmployeeLifecycleState;

final class EmployeeLifecycleStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'candidate' => ['offer_created'], 'offer_created' => ['hired'], 'hired' => ['onboarding'],
        'onboarding' => ['active'], 'active' => ['probation', 'terminated'],
        'probation' => ['confirmed', 'terminated'], 'confirmed' => ['leave', 'terminated'],
        'leave' => ['active'], 'terminated' => ['archived'], 'archived' => [],
    ];

    public function assertCanTransition(EmployeeLifecycleState $from, EmployeeLifecycleState $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages(['state' => "Không thể chuyển lifecycle từ {$from->value} sang {$to->value}."]);
        }
    }

    public function canTransition(EmployeeLifecycleState $from, EmployeeLifecycleState $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    public function assertCanChangeAssignment(EmployeeLifecycleState $state): void
    {
        if (! in_array($state, [EmployeeLifecycleState::Active, EmployeeLifecycleState::Probation, EmployeeLifecycleState::Confirmed], true)) {
            throw ValidationException::withMessages(['state' => "Trạng thái {$state->value} không cho phép điều chuyển hoặc thăng chức."]);
        }
    }
}
