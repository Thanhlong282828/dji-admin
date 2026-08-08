<?php

namespace Modules\Employee\Application\Commands\CreateEmployeeCredential;

use Modules\Employee\Domain\Models\EmployeeCredential;
use Modules\Employee\Domain\Services\EmployeeCredentialService;

final readonly class CreateEmployeeCredentialHandler
{
    public function __construct(private EmployeeCredentialService $service) {}

    public function handle(CreateEmployeeCredentialCommand $command): EmployeeCredential
    {
        return $this->service->create($command->companyId, $command->employeeId, $command->data);
    }
}
