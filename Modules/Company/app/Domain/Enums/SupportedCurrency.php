<?php

namespace Modules\Company\Domain\Enums;

enum SupportedCurrency: string
{
    case VND = 'VND';
    case USD = 'USD';
    case EUR = 'EUR';
    case JPY = 'JPY';
    case SGD = 'SGD';
    case AUD = 'AUD';
    case GBP = 'GBP';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function minorUnitExponent(): int
    {
        return match ($this) {
            self::VND, self::JPY => 0,
            self::USD, self::EUR, self::SGD, self::AUD, self::GBP => 2,
        };
    }

    public static function exponent(string $currency): int
    {
        return self::from(strtoupper(trim($currency)))->minorUnitExponent();
    }
}
