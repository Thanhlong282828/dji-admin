<?php

namespace Modules\Employee\Application\Queries\Employee360View;

final readonly class Employee360ViewQuery
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
