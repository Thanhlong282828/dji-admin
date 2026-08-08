<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Enums\ContractStatus;

final class EmploymentContractStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['approval'],
        'approval' => ['approved', 'draft', 'cancelled'],
        'approved' => ['active', 'cancelled'],
        'active' => ['expired', 'terminated'],
        'expired' => [],
        'terminated' => [],
        'cancelled' => [],
    ];

    public function assertCanTransition(ContractStatus $from, ContractStatus $to): void
    {
        if (! in_array($to->value, self::TRANSITIONS[$from->value], true)) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển hợp đồng từ {$from->value} sang {$to->value}.",
            ]);
        }
    }

    public function canTransition(ContractStatus $from, ContractStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }
}
