<?php

namespace Modules\Payroll\Application\Commands\ApproveGovernmentFiling;

final readonly class ApproveGovernmentFilingCommand
{
    public function __construct(public int $companyId, public int $filingId, public int $actorId) {}
}
