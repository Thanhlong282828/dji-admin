<?php

namespace Modules\Payroll\Application\Commands\ValidateGovernmentFiling;

final readonly class ValidateGovernmentFilingCommand
{
    public function __construct(public int $companyId, public int $filingId, public int $actorId) {}
}
