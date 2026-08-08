<?php

namespace Modules\Payroll\Domain\Services;

use InvalidArgumentException;

final readonly class TaxRoundingService
{
    public function percentage(int $baseMinor, int $basisPoints, string $rule): int
    {
        $numerator = $baseMinor * $basisPoints;

        return match ($rule) {
            'down' => intdiv($numerator, 10000),
            'half_up' => intdiv($numerator + 5000, 10000),
            default => throw new InvalidArgumentException('Unsupported tax rounding rule.'),
        };
    }
}
