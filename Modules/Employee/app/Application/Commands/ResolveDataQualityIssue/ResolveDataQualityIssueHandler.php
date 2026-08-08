<?php

namespace Modules\Employee\Application\Commands\ResolveDataQualityIssue;

use Modules\Employee\Domain\Services\HRDataGovernanceService;

final readonly class ResolveDataQualityIssueHandler
{
    public function __construct(private HRDataGovernanceService $service) {}

    public function handle(ResolveDataQualityIssueCommand $command): int
    {
        return $command->action === 'review'
            ? $this->service->reviewIssue($command->companyId, $command->issueId, $command->actorId, $command->idempotencyKey, $command->reason)
            : $this->service->resolveIssue($command->companyId, $command->issueId, $command->actorId, $command->idempotencyKey, $command->reason);
    }
}
