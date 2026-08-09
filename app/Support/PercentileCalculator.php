<?php

namespace App\Support;

final class PercentileCalculator
{
    /**
     * @param  array<int, int|float>  $values
     */
    public function calculate(array $values, float $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        $normalized = array_map(static fn (int|float $value): float => max(0.0, (float) $value), $values);
        sort($normalized, SORT_NUMERIC);
        $percentile = min(100.0, max(0.0, $percentile));
        $index = (int) ceil(($percentile / 100) * count($normalized)) - 1;

        return round($normalized[max(0, $index)], 2);
    }
}
