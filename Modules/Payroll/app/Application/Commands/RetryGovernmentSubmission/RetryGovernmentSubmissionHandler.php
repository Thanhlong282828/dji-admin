<?php

namespace Modules\Payroll\Application\Commands\RetryGovernmentSubmission;

use Modules\Payroll\Application\DTOs\GovernmentFilingResult;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;

final readonly class RetryGovernmentSubmissionHandler
{
    public function __construct(private GovernmentTaxFilingService $service) {}

    public function handle(RetryGovernmentSubmissionCommand $c): GovernmentFilingResult
    {
        return $this->service->submit($c->companyId, $c->filingId, $c->actorId, $c->idempotencyKey);
    }
}
