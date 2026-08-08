<?php

namespace Modules\Payroll\Application\Commands\RejectGovernmentFiling;

final readonly class RejectGovernmentFilingCommand
{
    public function __construct(public int $companyId, public int $filingId, public int $actorId, public string $reason) {}
}
