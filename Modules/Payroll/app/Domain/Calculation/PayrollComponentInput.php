<?php

namespace Modules\Payroll\Domain\Calculation;

use Modules\Payroll\Domain\Enums\PayrollCalculationType;
use Modules\Payroll\Domain\Enums\SalaryComponentCategory;

final readonly class PayrollComponentInput
{
    public function __construct(public int $componentId, public string $code, public string $name, public SalaryComponentCategory $category, public PayrollCalculationType $type, public string $currency, public ?int $amountMinor, public ?int $percentageBasisPoints, public ?int $quantityBasisPoints, public ?int $rateMinor, public bool $affectsGross, public bool $affectsNet) {}
}
