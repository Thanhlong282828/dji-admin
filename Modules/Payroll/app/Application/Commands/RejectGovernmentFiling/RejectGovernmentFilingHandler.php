<?php

namespace Modules\Payroll\Application\Commands\RejectGovernmentFiling;

use Modules\Payroll\Application\DTOs\GovernmentFilingResult;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;

final readonly class RejectGovernmentFilingHandler
{
    public function __construct(private GovernmentTaxFilingService $service) {}

    public function handle(RejectGovernmentFilingCommand $c): GovernmentFilingResult
    {
        return $this->service->reject($c->companyId, $c->filingId, $c->actorId, $c->reason);
    }
}
