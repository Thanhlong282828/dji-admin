<?php

namespace Tests\Unit\Performance;

use Modules\Performance\Domain\Enums\VarianceStatus;
use Modules\Performance\Domain\Services\ExecutionVarianceMath;
use PHPUnit\Framework\TestCase;

final class ExecutionVarianceMathTest extends TestCase
{
    public function test_variance_percentage_and_zero_target_are_deterministic(): void
    {
        self::assertSame(-2, ExecutionVarianceMath::variance(8, 10));
        self::assertSame(-2000, ExecutionVarianceMath::basisPoints(8, 10));
        self::assertSame(0, ExecutionVarianceMath::basisPoints(8, 0));
    }

    public function test_reforecast_uses_integer_minor_units(): void
    {
        self::assertSame(105_000_000, ExecutionVarianceMath::reforecast(100_000_000, 500));
        self::assertSame(87_500_000, ExecutionVarianceMath::reforecast(100_000_000, -1250));
    }

    public function test_status_classification_is_typed_and_fail_safe(): void
    {
        self::assertSame(VarianceStatus::Blocked, ExecutionVarianceMath::status(10, 10, true));
        self::assertSame(VarianceStatus::OnTarget, ExecutionVarianceMath::status(10, 10));
        self::assertSame(VarianceStatus::Warning, ExecutionVarianceMath::status(99, 100));
        self::assertSame(VarianceStatus::UnderPlan, ExecutionVarianceMath::status(80, 100));
        self::assertSame(VarianceStatus::OverPlan, ExecutionVarianceMath::status(120, 100));
    }
}
