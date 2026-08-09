<?php

namespace Modules\Shift\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ShiftDurationCalculator
{
    public function minutes(string $startsAt, string $endsAt, bool $crossesMidnight): int
    {
        $start = CarbonImmutable::createFromFormat('!H:i', substr($startsAt, 0, 5));
        $end = CarbonImmutable::createFromFormat('!H:i', substr($endsAt, 0, 5));
        if ($start === null || $end === null) {
            throw new InvalidArgumentException('Invalid shift time.');
        }
        if ($crossesMidnight) {
            $end = $end->addDay();
        }
        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('Shift end must be after start, or crosses_midnight must be enabled.');
        }

        return (int) $start->diffInMinutes($end);
    }
}
