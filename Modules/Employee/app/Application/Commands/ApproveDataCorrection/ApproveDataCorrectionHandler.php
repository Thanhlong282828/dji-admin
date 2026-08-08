<?php

namespace Modules\Employee\Application\Commands\ApproveDataCorrection;

use Modules\Employee\Domain\Services\HRDataGovernanceService;

final readonly class ApproveDataCorrectionHandler
{
    public function __construct(private HRDataGovernanceService $service) {}

    public function handle(ApproveDataCorrectionCommand $command): int
    {
        return $this->service->approveIssue($command->companyId, $command->issueId, $command->actorId, $command->idempotencyKey, $command->reason);
    }
}
