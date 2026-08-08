<?php

namespace Modules\Payroll\Application\Commands\SubmitGovernmentFiling;

final readonly class SubmitGovernmentFilingCommand
{
    public function __construct(public int $companyId, public int $filingId, public int $actorId, public string $idempotencyKey) {}
}
