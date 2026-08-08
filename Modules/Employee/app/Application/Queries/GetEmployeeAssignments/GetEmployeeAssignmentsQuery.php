<?php

namespace Modules\Employee\Application\Queries\GetEmployeeAssignments;

final readonly class GetEmployeeAssignmentsQuery
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
