<?php

namespace Modules\Attendance\Application\Queries;

final readonly class GetAttendancePeriodReadinessQuery
{
    public function __construct(public int $companyId, public int $payrollPeriodId) {}
}
