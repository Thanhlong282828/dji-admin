<?php

namespace App\Support;

final class SloCalculator
{
    /** @return array{window_minutes:int,total:int,failures:int,allowed_failures:int,consumed_percent:float,remaining_percent:float} */
    public function calculate(int $total, int $failures, float $targetPercent, int $windowMinutes): array
    {
        $total = max(0, $total);
        $failures = min($total, max(0, $failures));
        $targetPercent = min(100.0, max(0.0, $targetPercent));
        $allowed = (int) floor($total * ((100.0 - $targetPercent) / 100.0));
        $consumed = $allowed === 0 ? ($failures === 0 ? 0.0 : 100.0) : min(100.0, ($failures / $allowed) * 100.0);

        return [
            'window_minutes' => max(1, $windowMinutes),
            'total' => $total,
            'failures' => $failures,
            'allowed_failures' => $allowed,
            'consumed_percent' => round($consumed, 2),
            'remaining_percent' => round(max(0.0, 100.0 - $consumed), 2),
        ];
    }
}
