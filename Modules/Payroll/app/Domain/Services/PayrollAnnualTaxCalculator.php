<?php

namespace Modules\Payroll\Domain\Services;

use Modules\Payroll\Application\DTOs\AnnualTaxAggregateData;

final readonly class PayrollAnnualTaxCalculator
{
    public const VERSION = 'annual-tax-calculation-v1';

    /**
     * @param  array{tax_method:string,rounding_rule:string,floor_at_zero:bool,brackets:list<array{sequence:int,lower_minor:int,upper_minor:int|null,rate_basis_points:int}>}  $policy
     */
    public function liability(AnnualTaxAggregateData $aggregate, array $policy): int
    {
        $taxable = $policy['floor_at_zero'] ? max(0, $aggregate->taxableIncomeAfterDeductionsMinor) : $aggregate->taxableIncomeAfterDeductionsMinor;
        if ($taxable < 0) {
            return 0;
        }
        if ($policy['tax_method'] !== 'progressive') {
            throw new \DomainException('Annual Tax currently supports resident progressive policy snapshots only.');
        }
        $tax = 0;
        foreach ($policy['brackets'] as $bracket) {
            $portion = max(0, min($taxable, $bracket['upper_minor'] ?? $taxable) - $bracket['lower_minor']);
            $tax += $this->percentage($portion, $bracket['rate_basis_points'], $policy['rounding_rule']);
        }

        return $tax;
    }

    private function percentage(int $amount, int $basisPoints, string $rounding): int
    {
        $numerator = $amount * $basisPoints;

        return $rounding === 'down' ? intdiv($numerator, 10_000) : intdiv($numerator + 5_000, 10_000);
    }
}
