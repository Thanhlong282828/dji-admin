<?php

namespace Tests\Unit\Finance;

use Modules\Finance\Application\Services\BudgetExposureService;
use PHPUnit\Framework\TestCase;

final class BudgetExposureServiceTest extends TestCase
{
    public function test_canonical_formula_and_budget_evidence_states(): void
    {
        $service = new BudgetExposureService;

        self::assertSame([
            'budget' => 200_000_000,
            'actual' => 40_000_000,
            'open_commitment' => 60_000_000,
            'consumed' => 100_000_000,
            'remaining' => 100_000_000,
            'status' => 'WITHIN_BUDGET',
        ], $service->calculate(200_000_000, 40_000_000, 60_000_000));

        self::assertSame('WITHIN_BUDGET', $service->calculate(0, 0, 0, true)['status']);
        self::assertSame('NO_BUDGET', $service->calculate(0, 0, 0, false)['status']);
        self::assertSame('OVER_BUDGET', $service->calculate(0, 1, 0, true)['status']);
    }
}
