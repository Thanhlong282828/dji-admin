<?php

namespace Modules\Payroll\Domain\Services;

use InvalidArgumentException;
use Modules\Payroll\Application\DTOs\TaxBracketLine;
use Modules\Payroll\Application\DTOs\TaxCalculationResult;

final readonly class PersonalIncomeTaxCalculationService
{
    public function __construct(private TaxRoundingService $rounding) {}

    /** @param array{id:int,version:int,tax_method:string,rounding_rule:string,flat_rate_basis_points:int|null,brackets:list<array{sequence:int,lower:int,upper:int|null,rate:int}>} $policy */
    public function calculate(int $taxableIncomeMinor, array $policy): TaxCalculationResult
    {
        if ($policy['tax_method'] === 'flat') {
            $rate = $policy['flat_rate_basis_points'];
            if ($rate === null) {
                return new TaxCalculationResult($policy['id'], $policy['version'], $taxableIncomeMinor, $taxableIncomeMinor, 0, [], ['TAX_BRACKET_MISSING']);
            }

            return new TaxCalculationResult($policy['id'], $policy['version'], $taxableIncomeMinor, $taxableIncomeMinor, $this->rounding->percentage($taxableIncomeMinor, $rate, $policy['rounding_rule']), [], []);
        }
        if ($policy['tax_method'] !== 'progressive') {
            throw new InvalidArgumentException('Unsupported tax method.');
        }
        $lines = [];
        $total = 0;
        foreach ($policy['brackets'] as $bracket) {
            $upper = $bracket['upper'];
            $portion = max(0, min($taxableIncomeMinor, $upper ?? $taxableIncomeMinor) - $bracket['lower']);
            if ($portion === 0) {
                continue;
            }
            $amount = $this->rounding->percentage($portion, $bracket['rate'], $policy['rounding_rule']);
            $lines[] = new TaxBracketLine($bracket['sequence'], $bracket['lower'], $upper, $portion, $bracket['rate'], $amount);
            $total += $amount;
        }

        return new TaxCalculationResult($policy['id'], $policy['version'], $taxableIncomeMinor, $taxableIncomeMinor, $total, $lines, $policy['brackets'] === [] ? ['TAX_BRACKET_MISSING'] : []);
    }
}
