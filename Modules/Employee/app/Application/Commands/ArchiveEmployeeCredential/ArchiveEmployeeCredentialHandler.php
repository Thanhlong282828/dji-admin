<?php

namespace Modules\Employee\Application\Commands\ArchiveEmployeeCredential;

use Modules\Employee\Domain\Services\EmployeeCredentialService;

final readonly class ArchiveEmployeeCredentialHandler
{
    public function __construct(private EmployeeCredentialService $service) {}

    public function handle(ArchiveEmployeeCredentialCommand $command): void
    {
        $command->restore ? $this->service->restore($command->companyId, $command->credentialId) : $this->service->archive($command->companyId, $command->credentialId);
    }
}
