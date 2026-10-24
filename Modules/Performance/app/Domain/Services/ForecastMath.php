<?php

namespace Modules\Performance\Domain\Services;

use Carbon\CarbonImmutable;

final class ForecastMath
{
    public static function ratioBasisPoints(int $part, int $total): int
    {
        return $total > 0 ? intdiv($part * 10_000, $total) : 0;
    }

    public static function annualize(int $monthlyMinor): int
    {
        return $monthlyMinor * 12;
    }

    public static function applies(CarbonImmutable $effectiveMonth, CarbonImmutable $forecastMonth): bool
    {
        return $effectiveMonth->startOfMonth()->lessThanOrEqualTo($forecastMonth->startOfMonth());
    }

    public static function vacancy(int $approved, int $current): int
    {
        return max(0, $approved - $current);
    }
}
