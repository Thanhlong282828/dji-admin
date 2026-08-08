<?php

namespace Modules\Attendance\Application\Queries;

use Modules\Attendance\Application\DTOs\AttendanceRecordDetail;
use Modules\Attendance\Domain\Repositories\AttendanceWorkflowRepositoryInterface;

final readonly class GetAttendanceRecordDetailHandler
{
    public function __construct(private AttendanceWorkflowRepositoryInterface $repository) {}

    public function handle(GetAttendanceRecordDetailQuery $query): AttendanceRecordDetail
    {
        return $this->repository->detail($query->companyId, $query->recordId);
    }
}
