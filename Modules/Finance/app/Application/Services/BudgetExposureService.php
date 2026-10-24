<?php

namespace Modules\Finance\Application\Services;

/**
 * Canonical vocabulary for management Budget exposure.
 * Payment/AP/GRNI are settlement evidence and never consume Budget twice.
 */
final readonly class BudgetExposureService
{
    /** @return array{budget:int,actual:int,open_commitment:int,consumed:int,remaining:int,status:string} */
    public function calculate(int $budget, int $actual, int $openCommitment, bool $hasApprovedBudget = true): array
    {
        $consumption = $actual + $openCommitment;
        $remaining = $budget - $consumption;
        $status = match (true) {
            ! $hasApprovedBudget => 'NO_BUDGET',
            $remaining < 0 => 'OVER_BUDGET',
            default => 'WITHIN_BUDGET',
        };

        return [
            'budget' => $budget,
            'actual' => $actual,
            'open_commitment' => $openCommitment,
            'consumed' => $consumption,
            'remaining' => $remaining,
            'status' => $status,
        ];
    }
}
