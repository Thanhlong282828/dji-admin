<?php

namespace Modules\Performance\Domain\Services;

final class PlanningMath
{
    public static function variance(int $comparison, int $budget): int
    {
        return $comparison - $budget;
    }

    public static function varianceBasisPoints(int $comparison, int $budget): int
    {
        return $budget !== 0 ? intdiv(($comparison - $budget) * 10_000, $budget) : 0;
    }

    public static function vacancy(int $approved, int $current): int
    {
        return max(0, $approved - $current);
    }

    public static function annualize(int $monthlyMinor): int
    {
        return $monthlyMinor * 12;
    }
}
