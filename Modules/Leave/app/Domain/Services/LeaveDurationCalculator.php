<?php

namespace Modules\Leave\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class LeaveDurationCalculator
{
    /** @param list<string> $holidayDates */
    public function workingDays(CarbonImmutable $startsAt, CarbonImmutable $endsAt, array $holidayDates = []): float
    {
        if ($endsAt->lessThan($startsAt)) {
            throw new InvalidArgumentException('Leave end date cannot be before start date.');
        }
        $days = 0;
        for ($date = $startsAt->startOfDay(); $date->lessThanOrEqualTo($endsAt); $date = $date->addDay()) {
            if (! $date->isWeekend() && ! in_array($date->toDateString(), $holidayDates, true)) {
                $days++;
            }
        }

        return (float) $days;
    }

    /**
     * @param  array<int, int>  $plannedMinutesByIsoWeekday
     * @param  list<string>  $holidayDates
     */
    public function workingMinutes(CarbonImmutable $startsAt, CarbonImmutable $endsAt, array $plannedMinutesByIsoWeekday, array $holidayDates = []): int
    {
        if ($endsAt->lessThan($startsAt)) {
            throw new InvalidArgumentException('Leave end date cannot be before start date.');
        }
        $minutes = 0;
        for ($date = $startsAt->startOfDay(); $date->lessThanOrEqualTo($endsAt->startOfDay()); $date = $date->addDay()) {
            if (! in_array($date->toDateString(), $holidayDates, true)) {
                $minutes += $plannedMinutesByIsoWeekday[$date->dayOfWeekIso] ?? 0;
            }
        }

        return $minutes;
    }
}
