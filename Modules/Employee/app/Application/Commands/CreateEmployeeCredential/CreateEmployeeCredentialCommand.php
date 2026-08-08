<?php

namespace Modules\Employee\Application\Commands\CreateEmployeeCredential;

use Modules\Employee\Application\DTOs\EmployeeCredentialData;

final readonly class CreateEmployeeCredentialCommand
{
    public function __construct(public int $companyId, public int $employeeId, public EmployeeCredentialData $data, public int $actorId) {}
}
