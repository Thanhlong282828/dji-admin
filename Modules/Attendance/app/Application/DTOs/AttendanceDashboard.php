<?php

namespace Modules\Attendance\Application\DTOs;

final readonly class AttendanceDashboard
{
    public function __construct(public int $eventsToday, public int $openSessions, public int $pendingAdjustments, public int $anomalies) {}
}
