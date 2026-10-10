<?php

namespace Tests\Unit\Finance;

use Modules\Finance\Application\Services\ArFxSettlementCalculator;
use PHPUnit\Framework\TestCase;

final class ArFxSettlementCalculatorTest extends TestCase
{
    public function test_cumulative_release_final_residue_and_realized_signs(): void
    {
        $calculator = new ArFxSettlementCalculator;
        $first = $calculator->calculate(3, 10, 0, 0, 1, 4);
        self::assertSame(['released_carrying_minor' => 3, 'realized_fx_minor' => 1], $first);
        $second = $calculator->calculate(3, 10, 1, 3, 1, 2);
        self::assertSame(['released_carrying_minor' => 4, 'realized_fx_minor' => -2], $second);
        $final = $calculator->calculate(3, 10, 2, 7, 1, 3);
        self::assertSame(['released_carrying_minor' => 3, 'realized_fx_minor' => 0], $final);
    }

    public function test_over_settlement_fails_closed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ArFxSettlementCalculator)->calculate(100, 250, 80, 200, 21, 53);
    }
}
