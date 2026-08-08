<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;

final class PayrollRunStateMachine
{
    public function assert(PayrollRunStatus $from, PayrollRunStatus $to): void
    {
        $allowed = match ($from) {
            PayrollRunStatus::Draft => [PayrollRunStatus::Calculating, PayrollRunStatus::Cancelled],PayrollRunStatus::Calculating => [PayrollRunStatus::Calculated, PayrollRunStatus::ValidationFailed, PayrollRunStatus::Failed],PayrollRunStatus::Calculated => [PayrollRunStatus::Calculating, PayrollRunStatus::Submitted],PayrollRunStatus::ValidationFailed,PayrollRunStatus::Failed,PayrollRunStatus::Rejected => [PayrollRunStatus::Calculating, PayrollRunStatus::Cancelled],PayrollRunStatus::Submitted => [PayrollRunStatus::Approved, PayrollRunStatus::Rejected],PayrollRunStatus::Approved => [PayrollRunStatus::Finalized],PayrollRunStatus::Finalized,PayrollRunStatus::Cancelled => []
        };
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển Payroll Run từ {$from->value} sang {$to->value}."]);
        }
    }
}
