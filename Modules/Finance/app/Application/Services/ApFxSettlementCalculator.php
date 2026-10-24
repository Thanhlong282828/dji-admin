<?php

namespace Modules\Finance\Application\Services;

final class ApFxSettlementCalculator
{
    /** @return array{released_carrying_minor:int,realized_fx_minor:int} */
    public function calculate(int $originalForeignMinor, int $originalFunctionalMinor, int $previousForeignSettledMinor, int $previousReleasedMinor, int $currentForeignSettledMinor, int $settlementFunctionalMinor): array
    {
        if ($originalForeignMinor <= 0 || $originalFunctionalMinor < 0 || $previousForeignSettledMinor < 0 || $previousReleasedMinor < 0 || $currentForeignSettledMinor <= 0 || $settlementFunctionalMinor < 0 || $previousForeignSettledMinor + $currentForeignSettledMinor > $originalForeignMinor || $previousReleasedMinor > $originalFunctionalMinor) {
            throw new \InvalidArgumentException('AP_FX_SETTLEMENT_AMOUNT_INVALID');
        }
        $cumulative = $previousForeignSettledMinor + $currentForeignSettledMinor;
        $target = $cumulative === $originalForeignMinor ? $originalFunctionalMinor : intdiv(($originalFunctionalMinor * $cumulative) + intdiv($originalForeignMinor, 2), $originalForeignMinor);
        $released = $target - $previousReleasedMinor;
        if ($released < 0 || $target > $originalFunctionalMinor) {
            throw new \InvalidArgumentException('AP_FX_RELEASED_BASIS_INVALID');
        }

        return ['released_carrying_minor' => $released, 'realized_fx_minor' => $released - $settlementFunctionalMinor];
    }
}
