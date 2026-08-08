<?php

namespace Modules\Payroll\Application\Commands\GenerateAnnualTaxStatement;

final readonly class GenerateAnnualTaxStatementCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
