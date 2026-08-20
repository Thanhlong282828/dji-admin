<?php

namespace App\Support\Compensation;

final readonly class CompensationView
{
    public function __construct(
        public int $baseSalaryMinor,
        public int $fixedAllowanceMinor,
        public int $kpiTargetMinor,
        public int $otherVariableTargetMinor,
        public string $currency,
        public string $source,
        public ?string $effectiveFrom,
    ) {}

    public function targetGrossMinor(): int
    {
        return $this->baseSalaryMinor + $this->fixedAllowanceMinor + $this->kpiTargetMinor + $this->otherVariableTargetMinor;
    }

    /** @return array{base_salary_minor:int,fixed_allowance_minor:int,kpi_target_minor:int,other_variable_target_minor:int,target_gross_minor:int,currency:string,source:string,effective_from:string|null} */
    public function toArray(): array
    {
        return [
            'base_salary_minor' => $this->baseSalaryMinor,
            'fixed_allowance_minor' => $this->fixedAllowanceMinor,
            'kpi_target_minor' => $this->kpiTargetMinor,
            'other_variable_target_minor' => $this->otherVariableTargetMinor,
            'target_gross_minor' => $this->targetGrossMinor(),
            'currency' => $this->currency,
            'source' => $this->source,
            'effective_from' => $this->effectiveFrom,
        ];
    }
}
