<?php

namespace Modules\Payroll\Application\Commands\GenerateGovernmentDeclaration;

use Modules\Payroll\Application\DTOs\GovernmentFilingResult;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;

final readonly class GenerateGovernmentDeclarationHandler
{
    public function __construct(private GovernmentTaxFilingService $service) {}

    public function handle(GenerateGovernmentDeclarationCommand $c): GovernmentFilingResult
    {
        return $this->service->generate($c->companyId, $c->filingId, $c->actorId);
    }
}
