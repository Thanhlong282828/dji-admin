<?php

namespace Modules\Employee\Application\Commands\UpdateEmployeeCredential;

use Modules\Employee\Application\DTOs\EmployeeCredentialData;

final readonly class UpdateEmployeeCredentialCommand
{
    public function __construct(public int $companyId, public int $credentialId, public EmployeeCredentialData $data, public int $actorId) {}
}
