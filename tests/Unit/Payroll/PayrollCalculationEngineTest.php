<?php

namespace Tests\Unit\Payroll;

use DomainException;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Calculation\FixedComponentCalculator;
use Modules\Payroll\Domain\Calculation\PayrollCalculationEngine;
use Modules\Payroll\Domain\Calculation\PayrollComponentCalculatorResolver;
use Modules\Payroll\Domain\Calculation\PayrollComponentInput;
use Modules\Payroll\Domain\Calculation\PercentageComponentCalculator;
use Modules\Payroll\Domain\Calculation\QuantityRateComponentCalculator;
use Modules\Payroll\Domain\Enums\PayrollCalculationType;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Enums\SalaryComponentCategory;
use Modules\Payroll\Domain\Services\PayrollRunStateMachine;
use Modules\Payroll\Domain\ValueObjects\Money;
use Tests\TestCase;

final class PayrollCalculationEngineTest extends TestCase
{
    public function test_money_is_integer_safe_and_rejects_currency_mismatch(): void
    {
        self::assertSame(1250, (new Money(1000, 'VND'))->add(new Money(250, 'VND'))->minor);
        self::assertSame(333, (new Money(1000, 'VND'))->multiplyBasisPoints(3333)->minor);
        $this->expectException(DomainException::class);
        (new Money(1, 'VND'))->add(new Money(1, 'USD'));
    }

    public function test_generic_fixture_separates_gross_deduction_employer_and_net(): void
    {
        $result = $this->engine()->calculate([
            $this->input('BASIC', SalaryComponentCategory::Earning, PayrollCalculationType::Fixed, 5000, null, null, null),
            $this->input('ALLOW', SalaryComponentCategory::Earning, PayrollCalculationType::Fixed, 500, null, null, null),
            $this->input('OT', SalaryComponentCategory::Earning, PayrollCalculationType::QuantityRate, null, null, 100000, 20),
            $this->input('UNPAID', SalaryComponentCategory::Deduction, PayrollCalculationType::Fixed, 100, null, null, null),
            $this->input('EMPLOYER', SalaryComponentCategory::EmployerContribution, PayrollCalculationType::Fixed, 300, null, null, null),
        ]);
        self::assertTrue($result->valid());
        self::assertSame(5700, $result->grossMinor);
        self::assertSame(100, $result->deductionMinor);
        self::assertSame(300, $result->employerContributionMinor);
        self::assertSame(5600, $result->netMinor);
    }

    public function test_missing_rate_and_negative_net_are_validation_errors(): void
    {
        $missing = $this->engine()->calculate([$this->input('OT', SalaryComponentCategory::Earning, PayrollCalculationType::QuantityRate, null, null, 10000, null)]);
        self::assertFalse($missing->valid());
        self::assertStringContainsString('Thiếu rate', $missing->issues[0]);
        $negative = $this->engine()->calculate([$this->input('D', SalaryComponentCategory::Deduction, PayrollCalculationType::Fixed, 1, null, null, null)]);
        self::assertFalse($negative->valid());
        self::assertSame(-1, $negative->netMinor);
    }

    public function test_state_machine_blocks_unsafe_transition(): void
    {
        $this->expectException(ValidationException::class);
        (new PayrollRunStateMachine)->assert(PayrollRunStatus::Draft, PayrollRunStatus::Approved);
    }

    private function engine(): PayrollCalculationEngine
    {
        return new PayrollCalculationEngine(new PayrollComponentCalculatorResolver(new FixedComponentCalculator, new PercentageComponentCalculator, new QuantityRateComponentCalculator));
    }

    private function input(string $code, SalaryComponentCategory $category, PayrollCalculationType $type, ?int $amount, ?int $percentage, ?int $quantity, ?int $rate): PayrollComponentInput
    {
        return new PayrollComponentInput(1, $code, $code, $category, $type, 'VND', $amount, $percentage, $quantity, $rate, $category === SalaryComponentCategory::Earning, $category !== SalaryComponentCategory::EmployerContribution);
    }
}
