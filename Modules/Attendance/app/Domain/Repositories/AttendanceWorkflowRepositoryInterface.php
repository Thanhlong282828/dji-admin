<?php

namespace Modules\Attendance\Domain\Repositories;

use Modules\Attendance\Application\DTOs\AttendancePeriodReadiness;
use Modules\Attendance\Application\DTOs\AttendanceRecordDetail;

interface AttendanceWorkflowRepositoryInterface
{
    public function detail(int $companyId, int $recordId): AttendanceRecordDetail;

    public function readiness(int $companyId, int $payrollPeriodId): AttendancePeriodReadiness;

    public function readinessFresh(int $companyId, int $payrollPeriodId): AttendancePeriodReadiness;
}
