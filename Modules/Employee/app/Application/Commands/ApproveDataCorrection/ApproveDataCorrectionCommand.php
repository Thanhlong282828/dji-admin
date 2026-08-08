<?php

namespace Modules\Employee\Application\Commands\ApproveDataCorrection;

final readonly class ApproveDataCorrectionCommand
{
    public function __construct(public int $companyId, public int $issueId, public int $actorId, public string $reason, public string $idempotencyKey) {}
}
