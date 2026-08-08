<?php

namespace Tests\Unit\Payroll;

use Modules\Payroll\Application\DTOs\AnnualTaxAggregateData;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxCalculator;
use PHPUnit\Framework\TestCase;

final class PayrollAnnualTaxCalculatorTest extends TestCase
{
    public function test_progressive_boundaries_are_exact_integer_minor_units(): void
    {
        $calculator = new PayrollAnnualTaxCalculator;
        $policy = ['tax_method' => 'progressive', 'rounding_rule' => 'half_up', 'floor_at_zero' => true, 'brackets' => [['sequence' => 1, 'lower_minor' => 0, 'upper_minor' => 1_000_000, 'rate_basis_points' => 500], ['sequence' => 2, 'lower_minor' => 1_000_000, 'upper_minor' => 2_000_000, 'rate_basis_points' => 1000], ['sequence' => 3, 'lower_minor' => 2_000_000, 'upper_minor' => null, 'rate_basis_points' => 2000]]];
        self::assertSame(0, $calculator->liability($this->aggregate(0), $policy));
        self::assertSame(50_000, $calculator->liability($this->aggregate(1_000_000), $policy));
        self::assertSame(150_000, $calculator->liability($this->aggregate(2_000_000), $policy));
        self::assertSame(350_000, $calculator->liability($this->aggregate(3_000_000), $policy));
    }

    private function aggregate(int $taxable): AnnualTaxAggregateData
    {
        return new AnnualTaxAggregateData($taxable, $taxable, 0, 0, 0, 0, 0, $taxable, 0, 0, 1, 1, 'VND', 2026, 'VN');
    }
}
