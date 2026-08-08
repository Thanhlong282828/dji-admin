<?php

namespace Modules\Payroll\Application\Commands\ValidateGovernmentFiling;

use Modules\Payroll\Application\DTOs\GovernmentFilingResult;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;

final readonly class ValidateGovernmentFilingHandler
{
    public function __construct(private GovernmentTaxFilingService $service) {}

    public function handle(ValidateGovernmentFilingCommand $c): GovernmentFilingResult
    {
        return $this->service->validate($c->companyId, $c->filingId, $c->actorId);
    }
}
