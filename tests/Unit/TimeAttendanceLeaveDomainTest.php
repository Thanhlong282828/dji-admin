<?php

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Services\AttendanceCalculator;
use Modules\Leave\Domain\Enums\LeaveRequestStatus;
use Modules\Leave\Domain\Services\LeaveDurationCalculator;
use Modules\Leave\Domain\Services\LeaveRequestStateMachine;
use Modules\Shift\Domain\Services\ShiftDurationCalculator;
use PHPUnit\Framework\TestCase;

final class TimeAttendanceLeaveDomainTest extends TestCase
{
    public function test_cross_midnight_shift_duration_is_calculated(): void
    {
        self::assertSame(480, (new ShiftDurationCalculator)->minutes('22:00', '06:00', true));
    }

    public function test_attendance_metrics_are_kept_separate(): void
    {
        $result = (new AttendanceCalculator)->calculate(
            CarbonImmutable::parse('2026-07-24 08:00'),
            CarbonImmutable::parse('2026-07-24 17:00'),
            CarbonImmutable::parse('2026-07-24 08:20'),
            CarbonImmutable::parse('2026-07-24 17:45'),
            60, 5, 5, 30,
        );
        self::assertSame(540, $result->scheduledMinutes);
        self::assertSame(505, $result->workedMinutes);
        self::assertSame(15, $result->lateMinutes);
        self::assertSame(15, $result->overtimeMinutes);
        self::assertSame(AttendanceStatus::LATE, $result->status);
    }

    public function test_leave_duration_excludes_weekends_and_holidays(): void
    {
        $days = (new LeaveDurationCalculator)->workingDays(
            CarbonImmutable::parse('2026-07-23'),
            CarbonImmutable::parse('2026-07-27'),
            ['2026-07-24'],
        );
        self::assertSame(2.0, $days);
    }

    public function test_leave_state_machine_rejects_invalid_transition(): void
    {
        $this->expectException(DomainException::class);
        (new LeaveRequestStateMachine)->assertCanTransition(LeaveRequestStatus::REJECTED, LeaveRequestStatus::APPROVED);
    }
}
