<?php

namespace Modules\Performance\Application\Queries\EmployeePerformance360;

final readonly class EmployeePerformance360Query
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
