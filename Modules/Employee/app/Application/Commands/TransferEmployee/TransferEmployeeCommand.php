<?php

namespace Modules\Employee\Application\Commands\TransferEmployee;

use Modules\Employee\Application\DTOs\EmployeeAssignmentData;

final readonly class TransferEmployeeCommand
{
    public function __construct(public int $companyId, public int $employeeId, public int $currentAssignmentId, public EmployeeAssignmentData $data, public string $reason, public int $actorId, public bool $promotion = false) {}
}
