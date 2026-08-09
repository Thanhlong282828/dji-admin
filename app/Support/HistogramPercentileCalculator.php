<?php

namespace App\Support;

final class HistogramPercentileCalculator
{
    /** @param array<int, int> $cumulativeBuckets */
    public function calculate(array $cumulativeBuckets, int $count, float $percentile): float
    {
        if ($count <= 0 || $cumulativeBuckets === []) {
            return 0.0;
        }

        ksort($cumulativeBuckets, SORT_NUMERIC);
        $rank = (int) ceil($count * min(100.0, max(0.0, $percentile)) / 100);
        foreach ($cumulativeBuckets as $boundary => $observations) {
            if ($observations >= $rank) {
                return (float) $boundary;
            }
        }

        return (float) array_key_last($cumulativeBuckets);
    }
}
