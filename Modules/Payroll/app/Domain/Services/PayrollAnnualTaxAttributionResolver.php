<?php

namespace Modules\Payroll\Domain\Services;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PayrollAnnualTaxAttributionResolver
{
    public function regular(string $periodEnd): int
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($periodEnd, 0, 10));
        if (! $date instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Invalid authoritative Payroll period end.');
        }

        return (int) $date->format('Y');
    }

    public function adjustment(?int $persistedAttributionYear): ?int
    {
        return $persistedAttributionYear;
    }
}
