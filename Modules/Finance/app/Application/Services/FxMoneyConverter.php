<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Enums\SupportedCurrency;

final class FxMoneyConverter
{
    public function convertMinor(int $amountMinor, string $baseCurrency, string $quoteCurrency, string $rateDecimal): int
    {
        if ($amountMinor < 0 || ! is_numeric($rateDecimal) || ! preg_match('/^\d+(?:\.\d{1,12})?$/', $rateDecimal)) {
            throw ValidationException::withMessages(['amount' => 'FX_CONVERSION_INVALID_INPUT']);
        }
        if (bccomp($rateDecimal, '0', 12) !== 1) {
            throw ValidationException::withMessages(['amount' => 'FX_CONVERSION_INVALID_INPUT']);
        }
        try {
            $baseExponent = SupportedCurrency::exponent($baseCurrency);
            $quoteExponent = SupportedCurrency::exponent($quoteCurrency);
        } catch (\ValueError) {
            throw ValidationException::withMessages(['currency' => 'FX_CONVERSION_UNSUPPORTED_CURRENCY']);
        }

        if (strtoupper($baseCurrency) === strtoupper($quoteCurrency) && bccomp($rateDecimal, '1', 12) !== 0) {
            throw ValidationException::withMessages(['rate_decimal' => 'FX_CONVERSION_IDENTITY_RATE_REQUIRED']);
        }

        // Canonical contract: 1 BASE major unit = RATE QUOTE major units.
        $baseMajor = bcdiv((string) $amountMinor, bcpow('10', (string) $baseExponent, 0), 18);
        $quoteMajor = bcmul($baseMajor, $rateDecimal, 18);
        $scaled = bcmul($quoteMajor, bcpow('10', (string) $quoteExponent, 0), 18);
        [$whole, $fraction] = array_pad(explode('.', $scaled, 2), 2, '');

        return (int) $whole + ((int) ($fraction[0] ?? '0') >= 5 ? 1 : 0);
    }
}
