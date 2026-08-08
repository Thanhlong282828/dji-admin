<?php

namespace Modules\Payroll\Domain\Calculation;

use Modules\Payroll\Domain\Enums\SalaryComponentCategory;
use Throwable;

final readonly class PayrollCalculationEngine
{
    public function __construct(private PayrollComponentCalculatorResolver $resolver) {}

    /** @param list<PayrollComponentInput> $inputs */
    public function calculate(array $inputs): PayrollCalculationResult
    {
        $gross = 0;
        $deductions = 0;
        $employer = 0;
        $lines = [];
        $issues = [];
        $order = 0;
        foreach ($inputs as $input) {
            try {
                $amount = $this->resolver->resolve($input->type)->calculate($input, $gross);
                $lines[] = new PayrollCalculatedComponent($input, $amount, ++$order);
                match ($input->category) {
                    SalaryComponentCategory::Earning => $gross += $amount,SalaryComponentCategory::Deduction => $deductions += $amount,SalaryComponentCategory::EmployerContribution => $employer += $amount,SalaryComponentCategory::Informational => null
                };
            } catch (Throwable $e) {
                $issues[] = $e->getMessage();
            }
        } $net = $gross - $deductions;
        if ($net < 0) {
            $issues[] = 'Net amount không được âm.';
        }

        return new PayrollCalculationResult($gross, $deductions, $employer, $net, $lines, $issues);
    }
}
