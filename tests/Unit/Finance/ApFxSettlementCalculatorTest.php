<?php

namespace Tests\Unit\Finance;

use Modules\Finance\Application\Services\ApFxSettlementCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApFxSettlementCalculatorTest extends TestCase
{
    #[Test]
    public function it_releases_carrying_basis_cumulatively_and_uses_ap_gain_loss_direction(): void
    {
        $calculator = new ApFxSettlementCalculator;
        self::assertSame(['released_carrying_minor' => 3, 'realized_fx_minor' => -1], $calculator->calculate(3, 10, 0, 0, 1, 4));
        self::assertSame(['released_carrying_minor' => 4, 'realized_fx_minor' => 2], $calculator->calculate(3, 10, 1, 3, 1, 2));
        self::assertSame(['released_carrying_minor' => 3, 'realized_fx_minor' => 0], $calculator->calculate(3, 10, 2, 7, 1, 3));
    }

    #[Test]
    public function final_partial_payment_releases_the_exact_rounding_residue(): void
    {
        $calculator = new ApFxSettlementCalculator;
        self::assertSame(['released_carrying_minor' => 33, 'realized_fx_minor' => 0], $calculator->calculate(3, 100, 0, 0, 1, 33));
        self::assertSame(['released_carrying_minor' => 34, 'realized_fx_minor' => 0], $calculator->calculate(3, 100, 1, 33, 1, 34));
        self::assertSame(['released_carrying_minor' => 33, 'realized_fx_minor' => 0], $calculator->calculate(3, 100, 2, 67, 1, 33));
    }

    #[Test]
    #[DataProvider('invalidAmounts')]
    public function it_rejects_invalid_or_overpaid_settlements(array $arguments): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ApFxSettlementCalculator)->calculate(...$arguments);
    }

    /** @return iterable<string,array{0:array{int,int,int,int,int,int}}> */
    public static function invalidAmounts(): iterable
    {
        yield 'zero original' => [[0, 100, 0, 0, 1, 1]];
        yield 'zero payment' => [[10, 100, 0, 0, 0, 0]];
        yield 'overpayment' => [[10, 100, 9, 90, 2, 20]];
        yield 'released basis beyond original' => [[10, 100, 1, 101, 1, 10]];
        yield 'negative settlement' => [[10, 100, 0, 0, 1, -1]];
    }
}
