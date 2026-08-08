<?php

namespace Modules\Payroll\Domain\Services;

use InvalidArgumentException;
use Modules\Payroll\Domain\Enums\PayrollDownstreamSourceType;

final readonly class PayrollDownstreamSourceInvariant
{
    public function assertPayment(
        PayrollDownstreamSourceType $sourceType,
        ?int $payrollItemId,
        ?int $adjustmentRequestId,
        ?int $adjustmentRunId,
    ): void {
        if ($sourceType === PayrollDownstreamSourceType::RegularPayroll) {
            if ($payrollItemId === null || $adjustmentRequestId !== null || $adjustmentRunId !== null) {
                throw new InvalidArgumentException('A regular Payroll payment must reference only a Payroll Item.');
            }

            return;
        }

        if ($payrollItemId !== null || $adjustmentRequestId === null || $adjustmentRunId === null) {
            throw new InvalidArgumentException('An Adjustment payment must reference an Adjustment Request and Adjustment Run.');
        }
    }

    public function assertAccounting(
        PayrollDownstreamSourceType $sourceType,
        ?int $payrollRunId,
        ?int $adjustmentRequestId,
        ?int $adjustmentRunId,
    ): void {
        if ($sourceType === PayrollDownstreamSourceType::RegularPayroll) {
            if ($payrollRunId === null || $adjustmentRequestId !== null || $adjustmentRunId !== null) {
                throw new InvalidArgumentException('A regular Payroll journal must reference only a Payroll Run.');
            }

            return;
        }

        if ($payrollRunId !== null || $adjustmentRequestId === null || $adjustmentRunId === null) {
            throw new InvalidArgumentException('An Adjustment journal must reference an Adjustment Request and Adjustment Run.');
        }
    }
}
