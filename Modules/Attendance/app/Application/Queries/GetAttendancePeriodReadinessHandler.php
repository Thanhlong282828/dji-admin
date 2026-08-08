<?php

namespace Modules\Attendance\Application\Queries;

use Modules\Attendance\Application\DTOs\AttendancePeriodReadiness;
use Modules\Attendance\Domain\Repositories\AttendanceWorkflowRepositoryInterface;

final readonly class GetAttendancePeriodReadinessHandler
{
    public function __construct(private AttendanceWorkflowRepositoryInterface $repository) {}

    public function handle(GetAttendancePeriodReadinessQuery $query): AttendancePeriodReadiness
    {
        return $this->repository->readiness($query->companyId, $query->payrollPeriodId);
    }
}
