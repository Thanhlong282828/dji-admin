<?php

namespace Modules\Employee\Application\Commands\RecordHRGovernanceAccess;

use Modules\Employee\Domain\Services\HRGovernanceAuditService;

final readonly class RecordHRGovernanceAccessHandler
{
    public function __construct(private HRGovernanceAuditService $audit) {}

    public function handle(RecordHRGovernanceAccessCommand $command): void
    {
        $this->audit->append($command->companyId, $command->actorId, $command->entityType, $command->entityId, $command->action, $command->idempotencyKey);
    }
}
