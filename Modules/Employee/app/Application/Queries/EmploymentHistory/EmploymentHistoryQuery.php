<?php

namespace Modules\Employee\Application\Queries\EmploymentHistory;

final readonly class EmploymentHistoryQuery
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
