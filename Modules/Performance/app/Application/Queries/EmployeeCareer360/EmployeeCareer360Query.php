<?php

namespace Modules\Performance\Application\Queries\EmployeeCareer360;

final readonly class EmployeeCareer360Query
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
