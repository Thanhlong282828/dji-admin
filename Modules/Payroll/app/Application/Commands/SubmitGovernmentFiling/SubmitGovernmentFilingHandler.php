<?php

namespace Modules\Payroll\Application\Commands\SubmitGovernmentFiling;

use Modules\Payroll\Application\DTOs\GovernmentFilingResult;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;

final readonly class SubmitGovernmentFilingHandler
{
    public function __construct(private GovernmentTaxFilingService $service) {}

    public function handle(SubmitGovernmentFilingCommand $c): GovernmentFilingResult
    {
        return $this->service->submit($c->companyId, $c->filingId, $c->actorId, $c->idempotencyKey);
    }
}
