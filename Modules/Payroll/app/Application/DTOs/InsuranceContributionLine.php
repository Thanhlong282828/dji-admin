<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\InsuranceType;

final readonly class InsuranceContributionLine
{
    public function __construct(public InsuranceType $type, public string $party, public int $baseMinor, public int $rateBasisPoints, public int $amountMinor, public int $policyId, public int $policyVersion, public string $policyEffectiveFrom) {}
}
