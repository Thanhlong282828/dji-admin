<?php

namespace Modules\Payroll\Application\Commands\CreateAnnualTaxRefundIntent;

final readonly class CreateAnnualTaxRefundIntentCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
