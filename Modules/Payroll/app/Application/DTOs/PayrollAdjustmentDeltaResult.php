<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollAdjustmentDeltaResult
{
    /** @param list<array{code:string,type:string,historical:int,corrected:int,delta:int,currency:string,taxable:bool,insurable:bool,employer_cost:bool}> $components */
    public function __construct(public int $gross, public int $employeeInsurance, public int $employerInsurance, public int $tax, public int $net, public string $currency, public array $components) {}

    /** @return array{gross:int,employee_insurance:int,employer_insurance:int,tax:int,net:int,currency:string} */
    public function totals(): array
    {
        return ['gross' => $this->gross, 'employee_insurance' => $this->employeeInsurance, 'employer_insurance' => $this->employerInsurance, 'tax' => $this->tax, 'net' => $this->net, 'currency' => $this->currency];
    }
}
