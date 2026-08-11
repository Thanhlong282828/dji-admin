<?php

namespace App\Support\Performance;

final class PerformanceMath
{
    /** @param list<float|int> $values */
    public function percentile(array $values, int $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values, SORT_NUMERIC);
        $rank = (int) ceil(($percentile / 100) * count($values)) - 1;

        return round((float) $values[max(0, min(count($values) - 1, $rank))], 3);
    }

    public function rowsPerMegabyte(int $rows, int $bytes): float
    {
        return $bytes <= 0 ? 0.0 : round($rows / ($bytes / 1_048_576), 2);
    }
}
