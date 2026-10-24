<?php

namespace Modules\Finance\Domain\Enums;

enum FxRateType: string
{
    case Closing = 'closing';
    case Spot = 'spot';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
