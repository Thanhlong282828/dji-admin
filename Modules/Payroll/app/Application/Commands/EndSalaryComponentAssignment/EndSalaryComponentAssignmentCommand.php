<?php

namespace Modules\Payroll\Application\Commands\EndSalaryComponentAssignment;

final readonly class EndSalaryComponentAssignmentCommand
{
    public function __construct(public int $companyId, public int $assignmentId, public string $effectiveTo, public int $expectedVersion, public int $actorId) {}
}
