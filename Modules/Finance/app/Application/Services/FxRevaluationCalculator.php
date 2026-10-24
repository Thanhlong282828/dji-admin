<?php

namespace Modules\Finance\Application\Services;

final readonly class FxRevaluationCalculator
{
    public function __construct(private FxMoneyConverter $converter) {}

    /**
     * @param  numeric-string  $rate
     * @return array{revalued_functional_minor:int,fx_difference_minor:int}
     */
    public function calculate(int $foreignOutstandingMinor, int $carryingFunctionalMinor, string $rate, ?string $transactionCurrency = null, ?string $functionalCurrency = null): array
    {
        if (! preg_match('/^\d+(?:\.\d{1,12})?$/', $rate)) {
            throw new \InvalidArgumentException('FX_RATE_INVALID_RATE');
        }
        $revalued = $transactionCurrency !== null && $functionalCurrency !== null
            ? $this->converter->convertMinor($foreignOutstandingMinor, $transactionCurrency, $functionalCurrency, $rate)
            : (int) bcadd(bcmul((string) $foreignOutstandingMinor, $rate, 12), '0.5', 0);

        return ['revalued_functional_minor' => $revalued, 'fx_difference_minor' => $revalued - $carryingFunctionalMinor];
    }
}
