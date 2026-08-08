<?php

namespace Modules\Employee\Application\Commands\UpdateEmployeeCredential;

use Modules\Employee\Domain\Models\EmployeeCredential;
use Modules\Employee\Domain\Services\EmployeeCredentialService;

final readonly class UpdateEmployeeCredentialHandler
{
    public function __construct(private EmployeeCredentialService $service) {}

    public function handle(UpdateEmployeeCredentialCommand $command): EmployeeCredential
    {
        return $this->service->update($command->companyId, $command->credentialId, $command->data);
    }
}
