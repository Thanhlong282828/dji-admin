<?php

namespace Modules\Payroll\Application\Commands\RetryGovernmentSubmission;

final readonly class RetryGovernmentSubmissionCommand
{
    public function __construct(public int $companyId, public int $filingId, public int $actorId, public string $idempotencyKey) {}
}
