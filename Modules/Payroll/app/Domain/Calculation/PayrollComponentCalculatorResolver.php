<?php

namespace Modules\Payroll\Domain\Calculation;

use DomainException;
use Modules\Payroll\Domain\Enums\PayrollCalculationType;

final readonly class PayrollComponentCalculatorResolver
{
    public function __construct(private FixedComponentCalculator $fixed, private PercentageComponentCalculator $percentage, private QuantityRateComponentCalculator $quantityRate) {}

    public function resolve(PayrollCalculationType $type): PayrollComponentCalculatorInterface
    {
        return match ($type) {
            PayrollCalculationType::Fixed => $this->fixed,PayrollCalculationType::Percentage => $this->percentage,PayrollCalculationType::QuantityRate => $this->quantityRate,PayrollCalculationType::FormulaPlaceholder => throw new DomainException('Formula placeholder chưa có safe expression engine.')
        };
    }
}
