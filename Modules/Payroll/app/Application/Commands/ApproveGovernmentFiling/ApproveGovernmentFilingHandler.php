<?php

namespace Modules\Payroll\Application\Commands\ApproveGovernmentFiling;

use Modules\Payroll\Application\DTOs\GovernmentFilingResult;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;

final readonly class ApproveGovernmentFilingHandler
{
    public function __construct(private GovernmentTaxFilingService $service) {}

    public function handle(ApproveGovernmentFilingCommand $c): GovernmentFilingResult
    {
        return $this->service->approve($c->companyId, $c->filingId, $c->actorId);
    }
}
