<?php

namespace Modules\Attendance\Application\Queries\GetAttendanceDashboard;

final readonly class GetAttendanceDashboardQuery
{
    public function __construct(public int $companyId) {}
}
