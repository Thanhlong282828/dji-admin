<?php

namespace Modules\Payroll\Domain\Calculation;

final readonly class PayrollCalculatedComponent
{
    public function __construct(public PayrollComponentInput $input, public int $amountMinor, public int $order) {}
}
