<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class InsuranceCalculationResult
{
    /**
     * @param  list<InsuranceContributionLine>  $employeeLines
     * @param  list<InsuranceContributionLine>  $employerLines
     * @param  list<string>  $issues
     */
    public function __construct(public ?int $policyId, public ?int $policyVersion, public int $baseMinor, public array $employeeLines, public array $employerLines, public int $employeeTotalMinor, public int $employerTotalMinor, public array $issues) {}

    public function valid(): bool
    {
        return $this->issues === [];
    }
}
