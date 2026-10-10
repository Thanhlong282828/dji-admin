<?php

namespace Modules\Finance\Application\Services;

final class ArFxSettlementCalculator
{
    /** @return array{released_carrying_minor:int,realized_fx_minor:int} */
    public function calculate(int $originalForeignMinor, int $originalFunctionalMinor, int $previousForeignSettledMinor, int $previousReleasedMinor, int $currentForeignSettledMinor, int $settlementFunctionalMinor): array
    {
        if ($originalForeignMinor <= 0 || $originalFunctionalMinor < 0 || $previousForeignSettledMinor < 0 || $previousReleasedMinor < 0 || $currentForeignSettledMinor <= 0 || $settlementFunctionalMinor < 0 || $previousForeignSettledMinor + $currentForeignSettledMinor > $originalForeignMinor || $previousReleasedMinor > $originalFunctionalMinor) {
            throw new \InvalidArgumentException('AR_FX_SETTLEMENT_AMOUNT_INVALID');
        }
        $cumulativeForeign = $previousForeignSettledMinor + $currentForeignSettledMinor;
        $targetReleased = $cumulativeForeign === $originalForeignMinor
            ? $originalFunctionalMinor
            : intdiv(($originalFunctionalMinor * $cumulativeForeign) + intdiv($originalForeignMinor, 2), $originalForeignMinor);
        $released = $targetReleased - $previousReleasedMinor;
        if ($released < 0 || $targetReleased > $originalFunctionalMinor) {
            throw new \InvalidArgumentException('AR_FX_RELEASED_BASIS_INVALID');
        }

        return ['released_carrying_minor' => $released, 'realized_fx_minor' => $settlementFunctionalMinor - $released];
    }
}
