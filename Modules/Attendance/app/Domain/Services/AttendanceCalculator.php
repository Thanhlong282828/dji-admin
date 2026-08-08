<?php

namespace Modules\Attendance\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Attendance\Domain\Enums\AttendanceStatus;

final class AttendanceCalculator
{
    public function calculate(
        CarbonImmutable $scheduledStart,
        CarbonImmutable $scheduledEnd,
        ?CarbonImmutable $checkIn,
        ?CarbonImmutable $checkOut,
        int $breakMinutes = 0,
        int $lateGraceMinutes = 0,
        int $earlyGraceMinutes = 0,
        int $overtimeThresholdMinutes = 0,
    ): AttendanceResult {
        if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
            throw new InvalidArgumentException('Scheduled end must be after scheduled start.');
        }
        if ($breakMinutes < 0 || $lateGraceMinutes < 0 || $earlyGraceMinutes < 0 || $overtimeThresholdMinutes < 0) {
            throw new InvalidArgumentException('Minute rules cannot be negative.');
        }
        $scheduled = (int) $scheduledStart->diffInMinutes($scheduledEnd);
        if ($checkIn === null || $checkOut === null) {
            return new AttendanceResult($scheduled, 0, $breakMinutes, 0, 0, 0, AttendanceStatus::INCOMPLETE);
        }
        if ($checkOut->lessThan($checkIn)) {
            throw new InvalidArgumentException('Check-out must be after check-in.');
        }
        $worked = max(0, (int) $checkIn->diffInMinutes($checkOut) - $breakMinutes);
        $late = max(0, (int) $scheduledStart->diffInMinutes($checkIn, false) - $lateGraceMinutes);
        $early = max(0, (int) $checkOut->diffInMinutes($scheduledEnd, false) - $earlyGraceMinutes);
        $overtime = max(0, (int) $scheduledEnd->diffInMinutes($checkOut, false) - $overtimeThresholdMinutes);
        $status = match (true) {
            $late > 0 && $early > 0 => AttendanceStatus::LATE_AND_EARLY,
            $late > 0 => AttendanceStatus::LATE,
            $early > 0 => AttendanceStatus::EARLY_LEAVE,
            default => AttendanceStatus::PRESENT,
        };

        return new AttendanceResult($scheduled, $worked, $breakMinutes, $late, $early, $overtime, $status);
    }
}
