<?php

namespace Tests\Unit\Finance;

use Modules\Finance\Application\Services\FxMoneyConverter;
use Modules\Finance\Application\Services\FxRevaluationCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FxRevaluationCalculatorTest extends TestCase
{
    /** @return iterable<string,array{int,int,numeric-string,string,string,int,int}> */
    public static function transactionToFunctionalCases(): iterable
    {
        yield 'positive difference' => [100, 24000, '25000.000000000000', 'USD', 'VND', 25000, 1000];
        yield 'negative difference' => [100, 26000, '25000.000000000000', 'USD', 'VND', 25000, -1000];
        yield 'zero difference' => [100, 25000, '25000.000000000000', 'USD', 'VND', 25000, 0];
        yield 'decimal rate' => [3, 0, '1.250000000000', 'JPY', 'VND', 4, 4];
        yield 'rounding boundary down' => [1, 0, '1.499999999999', 'JPY', 'VND', 1, 1];
        yield 'rounding boundary up' => [1, 0, '1.500000000000', 'JPY', 'VND', 2, 2];
        yield 'large minor amount' => [1000000000, 0, '25000.123456789012', 'JPY', 'VND', 25000123456789, 25000123456789];
        yield 'small fractional rate' => [1000000, 0, '0.000001000000', 'JPY', 'VND', 1, 1];
    }

    #[DataProvider('transactionToFunctionalCases')]
    public function test_transaction_currency_is_converted_to_functional_currency_without_float_drift(
        int $foreign,
        int $carrying,
        string $rate,
        string $transactionCurrency,
        string $functionalCurrency,
        int $revalued,
        int $difference,
    ): void {
        self::assertTrue(is_numeric($rate));
        $calculator = new FxRevaluationCalculator(new FxMoneyConverter);
        $first = $calculator->calculate($foreign, $carrying, $rate, $transactionCurrency, $functionalCurrency);
        self::assertSame(['revalued_functional_minor' => $revalued, 'fx_difference_minor' => $difference], $first);
        self::assertSame($first, $calculator->calculate($foreign, $carrying, $rate, $transactionCurrency, $functionalCurrency));
    }

    public function test_invalid_rate_fails_closed(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FxRevaluationCalculator(new FxMoneyConverter))->calculate(100, 25000, '-1', 'USD', 'VND');
    }
}
