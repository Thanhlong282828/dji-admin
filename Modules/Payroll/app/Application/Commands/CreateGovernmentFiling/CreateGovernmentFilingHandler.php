<?php

namespace Modules\Payroll\Application\Commands\CreateGovernmentFiling;

use Modules\Payroll\Application\DTOs\GovernmentFilingResult;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;

final readonly class CreateGovernmentFilingHandler
{
    public function __construct(private GovernmentTaxFilingService $service) {}

    public function handle(CreateGovernmentFilingCommand $c): GovernmentFilingResult
    {
        return $this->service->create($c->companyId, $c->reconciliationId, $c->filingType, $c->actorId, $c->idempotencyKey);
    }
}
