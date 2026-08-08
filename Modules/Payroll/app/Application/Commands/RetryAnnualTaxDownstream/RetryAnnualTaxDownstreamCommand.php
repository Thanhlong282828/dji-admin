<?php

namespace Modules\Payroll\Application\Commands\RetryAnnualTaxDownstream;

final readonly class RetryAnnualTaxDownstreamCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
