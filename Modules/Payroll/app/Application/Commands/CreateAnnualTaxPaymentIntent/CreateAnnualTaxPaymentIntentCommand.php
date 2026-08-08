<?php

namespace Modules\Payroll\Application\Commands\CreateAnnualTaxPaymentIntent;

final readonly class CreateAnnualTaxPaymentIntentCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
