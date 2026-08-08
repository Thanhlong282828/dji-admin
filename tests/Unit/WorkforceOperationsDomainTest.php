<?php

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Attendance\Domain\Enums\AttendanceEventType;
use Modules\Attendance\Domain\Enums\RoundingRule;
use Modules\Attendance\Domain\Services\AttendanceCalculator;
use Modules\Attendance\Domain\Services\AttendanceEventPairingService;
use Modules\Attendance\Domain\Services\OvertimeEligibilityCalculator;
use Modules\Leave\Domain\Enums\LeaveRequestStatus;
use Modules\Leave\Domain\Services\LeaveDurationCalculator;
use Modules\Leave\Domain\Services\LeaveRequestStateMachine;
use PHPUnit\Framework\TestCase;

final class WorkforceOperationsDomainTest extends TestCase
{
    public function test_attendance_minutes_grace_break_and_overtime_are_exact(): void
    {
        $result = (new AttendanceCalculator)->calculate(CarbonImmutable::parse('2026-07-27 08:00'), CarbonImmutable::parse('2026-07-27 17:00'), CarbonImmutable::parse('2026-07-27 08:07'), CarbonImmutable::parse('2026-07-27 17:30'), 60, 5, 5, 15);
        self::assertSame(540, $result->scheduledMinutes);
        self::assertSame(503, $result->workedMinutes);
        self::assertSame(2, $result->lateMinutes);
        self::assertSame(15, $result->overtimeMinutes);
    }

    public function test_event_pairing_supports_multiple_sessions_and_missing_punch(): void
    {
        $service = new AttendanceEventPairingService;
        $paired = $service->pair([
            ['type' => AttendanceEventType::CheckIn, 'occurred_at' => CarbonImmutable::parse('2026-07-27 01:00Z')],
            ['type' => AttendanceEventType::CheckOut, 'occurred_at' => CarbonImmutable::parse('2026-07-27 05:00Z')],
            ['type' => AttendanceEventType::CheckIn, 'occurred_at' => CarbonImmutable::parse('2026-07-27 06:00Z')],
        ]);
        self::assertSame(240, $paired->grossMinutes);
        self::assertCount(1, $paired->sessions);
        self::assertContains('missing_check_out', $paired->anomalies);
    }

    public function test_rounding_and_payable_overtime_are_integer_minutes(): void
    {
        self::assertSame(30, RoundingRule::Nearest15->apply(29));
        self::assertSame(15, RoundingRule::Floor15->apply(29));
        self::assertSame(30, RoundingRule::Ceil15->apply(16));
        self::assertSame(90, (new OvertimeEligibilityCalculator)->payableMinutes(120, 90, 30, 180));
        self::assertSame(0, (new OvertimeEligibilityCalculator)->payableMinutes(20, 20, 30, 180));
    }

    public function test_leave_minutes_use_calendar_and_exclude_holiday(): void
    {
        $minutes = (new LeaveDurationCalculator)->workingMinutes(CarbonImmutable::parse('2026-07-27'), CarbonImmutable::parse('2026-07-31'), [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480], ['2026-07-29']);
        self::assertSame(1920, $minutes);
    }

    public function test_leave_state_machine_requires_submit_before_approval(): void
    {
        $machine = new LeaveRequestStateMachine;
        $machine->assertCanTransition(LeaveRequestStatus::DRAFT, LeaveRequestStatus::SUBMITTED);
        $this->expectException(DomainException::class);
        $machine->assertCanTransition(LeaveRequestStatus::DRAFT, LeaveRequestStatus::APPROVED);
    }
}
