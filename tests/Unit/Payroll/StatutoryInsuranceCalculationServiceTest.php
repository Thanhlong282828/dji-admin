<?php

namespace Tests\Unit\Payroll;

use Modules\Payroll\Application\DTOs\InsuranceContributionBase;
use Modules\Payroll\Domain\Services\InsuranceRoundingService;
use Modules\Payroll\Domain\Services\StatutoryInsuranceCalculationService;
use PHPUnit\Framework\TestCase;

final class StatutoryInsuranceCalculationServiceTest extends TestCase
{
    public function test_employee_and_employer_lines_use_integer_basis_points_caps_floors_and_deterministic_rounding(): void
    {
        $service = new StatutoryInsuranceCalculationService(new InsuranceRoundingService);
        $policy = ['id' => 7, 'version' => 3, 'effective_from' => '2026-01-01', 'currency_code' => 'VND', 'rounding_rule' => 'half_up'];
        $rates = [
            ['insurance_type' => 'social', 'employee_rate_basis_points' => 800, 'employer_rate_basis_points' => 1750, 'minimum_base_minor' => 2_000_000, 'maximum_base_minor' => 20_000_000],
            ['insurance_type' => 'health', 'employee_rate_basis_points' => 150, 'employer_rate_basis_points' => 300, 'minimum_base_minor' => null, 'maximum_base_minor' => 10_000_000],
        ];
        $result = $service->calculate(new InsuranceContributionBase(12_345_678, [1], [2], null, 12_345_678, 'VND', 'eligible_components'), $policy, $rates);
        self::assertSame(987_654 + 150_000, $result->employeeTotalMinor);
        self::assertSame(2_160_494 + 300_000, $result->employerTotalMinor);
        self::assertSame(12_345_678, $result->employerLines[0]->baseMinor);
        self::assertSame(10_000_000, $result->employeeLines[1]->baseMinor);
        self::assertTrue($result->valid());
    }

    public function test_round_down_never_uses_binary_float(): void
    {
        $rounding = new InsuranceRoundingService;
        self::assertSame(333, $rounding->percentage(10_001, 333, 'down'));
        self::assertSame(333, $rounding->percentage(10_001, 333, 'half_up'));
    }
}
