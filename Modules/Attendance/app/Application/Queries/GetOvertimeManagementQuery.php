<?php

namespace Modules\Attendance\Application\Queries;

final readonly class GetOvertimeManagementQuery
{
    public function __construct(public int $companyId) {}
}
