<?php

namespace Modules\Payroll\Application\Commands\GenerateGovernmentDeclaration;

final readonly class GenerateGovernmentDeclarationCommand
{
    public function __construct(public int $companyId, public int $filingId, public int $actorId) {}
}
