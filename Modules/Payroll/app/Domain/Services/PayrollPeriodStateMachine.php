<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;

final class PayrollPeriodStateMachine
{
    public function assertCanTransition(PayrollPeriodStatus $from, PayrollPeriodStatus $to): void
    {
        $allowed = match ($from) {
            PayrollPeriodStatus::Draft => [PayrollPeriodStatus::Collecting, PayrollPeriodStatus::Cancelled],PayrollPeriodStatus::Collecting => [PayrollPeriodStatus::Review, PayrollPeriodStatus::Locked, PayrollPeriodStatus::Cancelled],PayrollPeriodStatus::Review => [PayrollPeriodStatus::Collecting, PayrollPeriodStatus::Locked, PayrollPeriodStatus::Cancelled],PayrollPeriodStatus::Locked => [PayrollPeriodStatus::Processed],PayrollPeriodStatus::Processed,PayrollPeriodStatus::Cancelled => []
        };
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển payroll period từ {$from->value} sang {$to->value}."]);
        }
    }
}
