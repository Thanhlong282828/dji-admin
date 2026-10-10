<?php

namespace Tests\Unit\Finance;

use InvalidArgumentException;
use Modules\Finance\Application\Services\ApFxCreditCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApFxCreditCalculatorTest extends TestCase
{
    /** @param array<string,int> $expected */
    #[DataProvider('validCases')]
    public function test_it_releases_ap_carrying_basis_and_creates_paid_portion_receivable(array $input, array $expected): void
    {
        self::assertSame($expected, (new ApFxCreditCalculator)->calculate(...$input));
    }

    /** @return iterable<string,array{0:array<int,int>,1:array<string,int>}> */
    public static function validCases(): iterable
    {
        yield 'unpaid partial credit' => [
            [10_000, 250_000_000, 0, 0, 0, 0, 0, 4_000],
            ['ap_foreign_minor' => 4_000, 'ap_carrying_release_minor' => 100_000_000, 'supplier_receivable_foreign_minor' => 0, 'supplier_receivable_functional_minor' => 0],
        ];
        yield 'partially paid mixed credit' => [
            [10_000, 250_000_000, 7_000, 175_000_000, 0, 0, 0, 5_000],
            ['ap_foreign_minor' => 3_000, 'ap_carrying_release_minor' => 75_000_000, 'supplier_receivable_foreign_minor' => 2_000, 'supplier_receivable_functional_minor' => 50_000_000],
        ];
        yield 'active revaluation is released with final AP credit' => [
            [10_000, 250_000_000, 4_000, 100_000_000, 2_000, 50_000_000, 8_000_000, 4_000],
            ['ap_foreign_minor' => 4_000, 'ap_carrying_release_minor' => 108_000_000, 'supplier_receivable_foreign_minor' => 0, 'supplier_receivable_functional_minor' => 0],
        ];
        yield 'fully paid credit becomes supplier receivable' => [
            [10_000, 250_000_000, 10_000, 250_000_000, 0, 0, 0, 2_000],
            ['ap_foreign_minor' => 0, 'ap_carrying_release_minor' => 0, 'supplier_receivable_foreign_minor' => 2_000, 'supplier_receivable_functional_minor' => 50_000_000],
        ];
    }

    public function test_it_rejects_credit_when_prior_economic_releases_exceed_recognition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AP_FX_CREDIT_AMOUNT_INVALID');

        (new ApFxCreditCalculator)->calculate(10_000, 250_000_000, 9_000, 225_000_000, 2_000, 50_000_000, 0, 1_000);
    }
}
