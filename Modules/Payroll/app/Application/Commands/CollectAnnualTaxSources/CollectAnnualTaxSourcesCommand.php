<?php

namespace Modules\Payroll\Application\Commands\CollectAnnualTaxSources;

final readonly class CollectAnnualTaxSourcesCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
