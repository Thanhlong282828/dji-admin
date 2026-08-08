<?php

namespace Modules\Attendance\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Attendance\Domain\Enums\AttendanceEventType;

final class AttendanceEventPairingService
{
    /** @param list<array{type:AttendanceEventType,occurred_at:CarbonImmutable}> $events */
    public function pair(array $events): AttendanceEventPairingResult
    {
        usort($events, static fn (array $a, array $b): int => $a['occurred_at']->getTimestamp() <=> $b['occurred_at']->getTimestamp());
        $open = null;
        $breakStart = null;
        $sessions = [];
        $gross = 0;
        $breaks = 0;
        $anomalies = [];
        foreach ($events as $event) {
            if ($event['type']->isIn()) {
                if ($open !== null) {
                    $anomalies[] = 'overlapping_session';
                } else {
                    $open = $event['occurred_at'];
                }
            } elseif ($event['type']->isOut()) {
                if ($open === null) {
                    $anomalies[] = 'missing_check_in';
                } else {
                    $minutes = (int) $open->diffInMinutes($event['occurred_at']);
                    $sessions[] = ['in' => $open->toIso8601String(), 'out' => $event['occurred_at']->toIso8601String(), 'minutes' => $minutes];
                    $gross += $minutes;
                    $open = null;
                }
            } elseif ($event['type'] === AttendanceEventType::BreakStart) {
                $breakStart = $event['occurred_at'];
            } elseif ($event['type'] === AttendanceEventType::BreakEnd && $breakStart !== null) {
                $breaks += (int) $breakStart->diffInMinutes($event['occurred_at']);
                $breakStart = null;
            }
        }
        if ($open !== null) {
            $anomalies[] = 'missing_check_out';
        }
        if ($gross > 16 * 60) {
            $anomalies[] = 'excessive_hours';
        }

        return new AttendanceEventPairingResult($sessions, $gross, $breaks, array_values(array_unique($anomalies)));
    }
}
