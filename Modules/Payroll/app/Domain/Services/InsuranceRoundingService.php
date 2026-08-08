<?php

namespace Modules\Payroll\Domain\Services;

use InvalidArgumentException;

final class InsuranceRoundingService
{
    public function percentage(int $baseMinor, int $basisPoints, string $rule): int
    {
        if ($baseMinor < 0 || $basisPoints < 0) {
            throw new InvalidArgumentException('Insurance base and rate must be non-negative.');
        }
        $numerator = $baseMinor * $basisPoints;

        return match ($rule) {
            'down' => intdiv($numerator, 10000),
            'half_up' => intdiv($numerator + 5000, 10000),
            default => throw new InvalidArgumentException('Unsupported insurance rounding rule.'),
        };
    }
}
