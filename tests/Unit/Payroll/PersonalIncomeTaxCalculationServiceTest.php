<?php

namespace Tests\Unit\Payroll;

use InvalidArgumentException;
use Modules\Payroll\Domain\Services\PersonalIncomeTaxCalculationService;
use Modules\Payroll\Domain\Services\TaxRoundingService;
use PHPUnit\Framework\TestCase;

final class PersonalIncomeTaxCalculationServiceTest extends TestCase
{
    public function test_progressive_marginal_boundaries_and_open_top_bracket_are_deterministic(): void
    {
        $service = new PersonalIncomeTaxCalculationService(new TaxRoundingService);
        $policy = ['id' => 1, 'version' => 2, 'tax_method' => 'progressive', 'rounding_rule' => 'half_up', 'flat_rate_basis_points' => null, 'brackets' => [['sequence' => 1, 'lower' => 0, 'upper' => 1_000_000, 'rate' => 500], ['sequence' => 2, 'lower' => 1_000_000, 'upper' => 2_000_000, 'rate' => 1000], ['sequence' => 3, 'lower' => 2_000_000, 'upper' => null, 'rate' => 2000]]];
        self::assertSame(0, $service->calculate(0, $policy)->withholdingTaxMinor);
        self::assertSame(50_000, $service->calculate(1_000_000, $policy)->withholdingTaxMinor);
        self::assertSame(50_000, $service->calculate(1_000_001, $policy)->withholdingTaxMinor);
        $result = $service->calculate(3_000_000, $policy);
        self::assertSame(350_000, $result->withholdingTaxMinor);
        self::assertSame([1_000_000, 1_000_000, 1_000_000], array_map(static fn ($line): int => $line->taxablePortionMinor, $result->bracketLines));
    }

    public function test_flat_policy_and_rounding_do_not_use_float(): void
    {
        $service = new PersonalIncomeTaxCalculationService(new TaxRoundingService);
        $policy = ['id' => 2, 'version' => 1, 'tax_method' => 'flat', 'rounding_rule' => 'down', 'flat_rate_basis_points' => 333, 'brackets' => []];
        self::assertSame(333, $service->calculate(10_001, $policy)->withholdingTaxMinor);
    }

    public function test_unknown_method_is_rejected(): void
    {
        $service = new PersonalIncomeTaxCalculationService(new TaxRoundingService);
        $this->expectException(InvalidArgumentException::class);
        $service->calculate(1, ['id' => 2, 'version' => 1, 'tax_method' => 'formula', 'rounding_rule' => 'down', 'flat_rate_basis_points' => null, 'brackets' => []]);
    }
}
