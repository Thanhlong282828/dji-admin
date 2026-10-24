<?php

namespace Modules\Finance\Application\Services;

final class ApFxCreditCalculator
{
    /** @return array{ap_foreign_minor:int,ap_carrying_release_minor:int,supplier_receivable_foreign_minor:int,supplier_receivable_functional_minor:int} */
    public function calculate(
        int $originalForeignMinor,
        int $originalFunctionalMinor,
        int $settledForeignMinor,
        int $settledCarryingMinor,
        int $priorCreditedForeignMinor,
        int $priorCreditCarryingMinor,
        int $priorActiveRevaluationMinor,
        int $currentCreditForeignMinor,
    ): array {
        if ($originalForeignMinor <= 0 || $originalFunctionalMinor < 0 || $settledForeignMinor < 0 || $settledCarryingMinor < 0
            || $priorCreditedForeignMinor < 0 || $priorCreditCarryingMinor < 0 || $currentCreditForeignMinor <= 0
            || $settledForeignMinor + $priorCreditedForeignMinor > $originalForeignMinor) {
            throw new \InvalidArgumentException('AP_FX_CREDIT_AMOUNT_INVALID');
        }

        $outstandingForeign = $originalForeignMinor - $settledForeignMinor - $priorCreditedForeignMinor;
        $outstandingCarrying = $originalFunctionalMinor - $settledCarryingMinor - $priorCreditCarryingMinor + $priorActiveRevaluationMinor;
        if ($outstandingCarrying < 0) {
            throw new \InvalidArgumentException('AP_FX_CREDIT_CARRYING_BASIS_INVALID');
        }

        $apForeign = min($currentCreditForeignMinor, $outstandingForeign);
        $apRelease = $apForeign === 0
            ? 0
            : ($apForeign === $outstandingForeign
                ? $outstandingCarrying
                : $this->proportion($outstandingCarrying, $apForeign, $outstandingForeign));
        $receivableForeign = $currentCreditForeignMinor - $apForeign;
        $receivableFunctional = $receivableForeign === 0
            ? 0
            : $this->proportion($originalFunctionalMinor, $receivableForeign, $originalForeignMinor);

        return [
            'ap_foreign_minor' => $apForeign,
            'ap_carrying_release_minor' => $apRelease,
            'supplier_receivable_foreign_minor' => $receivableForeign,
            'supplier_receivable_functional_minor' => $receivableFunctional,
        ];
    }

    private function proportion(int $functionalMinor, int $foreignMinor, int $foreignTotal): int
    {
        return intdiv(($functionalMinor * $foreignMinor) + intdiv($foreignTotal, 2), $foreignTotal);
    }
}
