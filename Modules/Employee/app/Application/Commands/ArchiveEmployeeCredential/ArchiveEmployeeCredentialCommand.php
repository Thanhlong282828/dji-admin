<?php

namespace Modules\Employee\Application\Commands\ArchiveEmployeeCredential;

final readonly class ArchiveEmployeeCredentialCommand
{
    public function __construct(public int $companyId, public int $credentialId, public int $actorId, public bool $restore = false) {}
}
