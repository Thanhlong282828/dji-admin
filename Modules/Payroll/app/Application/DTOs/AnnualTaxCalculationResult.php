<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollAnnualTaxResultType;

final readonly class AnnualTaxCalculationResult
{
    public function __construct(public int $reconciliationId, public PayrollAnnualTaxResultType $resultType, public AnnualTaxAggregateData $aggregate, public int $finalTaxLiabilityMinor, public int $additionalTaxPayableMinor, public int $taxRefundMinor, public int $calculationVersion, public string $sourceChecksum) {}
}
