<?php

namespace Modules\Leave\Domain\Services;

use DomainException;
use Modules\Leave\Domain\Enums\LeaveRequestStatus;

final class LeaveRequestStateMachine
{
    /** @var array<string, list<LeaveRequestStatus>> */
    private const TRANSITIONS = [
        'draft' => [LeaveRequestStatus::SUBMITTED, LeaveRequestStatus::CANCELLED],
        'submitted' => [LeaveRequestStatus::APPROVED, LeaveRequestStatus::REJECTED, LeaveRequestStatus::CANCELLED, LeaveRequestStatus::WITHDRAWN],
        'approved' => [LeaveRequestStatus::CANCELLED],
        'rejected' => [],
        'cancelled' => [],
        'withdrawn' => [],
    ];

    public function assertCanTransition(LeaveRequestStatus $from, LeaveRequestStatus $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$from->value], true)) {
            throw new DomainException("Cannot transition leave request from {$from->value} to {$to->value}.");
        }
    }
}
