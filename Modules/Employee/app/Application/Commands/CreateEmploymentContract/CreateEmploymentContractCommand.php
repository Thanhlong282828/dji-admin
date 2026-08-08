<?php

namespace Modules\Employee\Application\Commands\CreateEmploymentContract;

use Modules\Employee\Application\DTOs\EmploymentContractData;

final readonly class CreateEmploymentContractCommand
{
    public function __construct(public int $companyId, public int $employeeId, public EmploymentContractData $data, public int $actorId) {}
}
