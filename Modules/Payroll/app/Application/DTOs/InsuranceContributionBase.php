<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class InsuranceContributionBase
{
    /**
     * @param  list<int>  $includedComponentIds
     * @param  list<int>  $excludedComponentIds
     */
    public function __construct(public int $rawBaseMinor, public array $includedComponentIds, public array $excludedComponentIds, public ?int $overrideMinor, public int $finalBaseMinor, public string $currency, public string $resolutionReason) {}
}
