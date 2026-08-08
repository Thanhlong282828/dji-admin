<?php

namespace Modules\Attendance\Domain\Services;

use Modules\Attendance\Domain\Enums\AttendanceStatus;

final readonly class AttendanceResult
{
    public function __construct(
        public int $scheduledMinutes,
        public int $workedMinutes,
        public int $breakMinutes,
        public int $lateMinutes,
        public int $earlyLeaveMinutes,
        public int $overtimeMinutes,
        public AttendanceStatus $status,
    ) {}
}
