<?php

namespace Modules\Employee\Application\Commands\RecordHRGovernanceAccess;

final readonly class RecordHRGovernanceAccessCommand
{
    public function __construct(public int $companyId, public int $actorId, public string $entityType, public int $entityId, public string $action, public string $idempotencyKey) {}
}
