<?php

namespace Modules\Attendance\Application\Queries;

final readonly class GetAttendanceRecordDetailQuery
{
    public function __construct(public int $companyId, public int $recordId) {}
}
