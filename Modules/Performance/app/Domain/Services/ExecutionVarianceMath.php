<?php

namespace Modules\Performance\Domain\Services;

use Modules\Performance\Domain\Enums\VarianceStatus;

final class ExecutionVarianceMath
{
    public static function variance(int $actual, int $target): int
    {
        return $actual - $target;
    }

    public static function basisPoints(int $actual, int $target): int
    {
        return $target === 0 ? 0 : intdiv(($actual - $target) * 10_000, $target);
    }

    public static function reforecast(int $forecast, int $adjustmentBasisPoints): int
    {
        return max(0, $forecast + intdiv($forecast * $adjustmentBasisPoints, 10_000));
    }

    public static function status(int $actual, int $target, bool $blocked = false): VarianceStatus
    {
        if ($blocked) {
            return VarianceStatus::Blocked;
        }
        if ($actual === $target) {
            return VarianceStatus::OnTarget;
        }
        if ($target > 0 && abs(self::basisPoints($actual, $target)) <= 500) {
            return VarianceStatus::Warning;
        }

        return $actual < $target ? VarianceStatus::UnderPlan : VarianceStatus::OverPlan;
    }
}
