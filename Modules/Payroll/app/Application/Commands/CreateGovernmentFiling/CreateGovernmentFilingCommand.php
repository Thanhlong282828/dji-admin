<?php

namespace Modules\Payroll\Application\Commands\CreateGovernmentFiling;

final readonly class CreateGovernmentFilingCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public string $filingType, public int $actorId, public string $idempotencyKey) {}
}
