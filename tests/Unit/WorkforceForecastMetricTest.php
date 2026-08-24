<?php

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\Performance\Domain\Enums\ForecastMeasureType;
use Modules\Performance\Domain\Services\ForecastMath;
use PHPUnit\Framework\TestCase;

final class WorkforceForecastMetricTest extends TestCase
{
    public function test_money_headcount_and_ratio_math_is_integer_and_deterministic(): void
    {
        self::assertSame(1_200_000, ForecastMath::annualize(100_000));
        self::assertSame(0, ForecastMath::ratioBasisPoints(3, 0));
        self::assertSame(15_000, ForecastMath::ratioBasisPoints(3, 2));
        self::assertSame(0, ForecastMath::vacancy(2, 5));
        self::assertSame(3, ForecastMath::vacancy(5, 2));
    }

    public function test_effective_month_does_not_apply_early(): void
    {
        self::assertFalse(ForecastMath::applies(new CarbonImmutable('2026-10-01'), new CarbonImmutable('2026-09-01')));
        self::assertTrue(ForecastMath::applies(new CarbonImmutable('2026-10-15'), new CarbonImmutable('2026-10-01')));
    }

    public function test_measure_types_keep_actual_budget_forecast_and_scenario_distinct(): void
    {
        self::assertSame(['actual', 'budget', 'forecast', 'scenario'], array_column(ForecastMeasureType::cases(), 'value'));
    }
}
