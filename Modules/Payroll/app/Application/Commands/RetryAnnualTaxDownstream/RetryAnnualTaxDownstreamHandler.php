<?php

namespace Modules\Payroll\Application\Commands\RetryAnnualTaxDownstream;

use Modules\Payroll\Application\DTOs\AnnualTaxDocumentData;
use Modules\Payroll\Application\DTOs\AnnualTaxDownstreamResult;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxDeliveryService;

final readonly class RetryAnnualTaxDownstreamHandler
{
    public function __construct(private PayrollAnnualTaxDeliveryService $service) {}

    /** @return array{AnnualTaxDownstreamResult,AnnualTaxDownstreamResult,AnnualTaxDownstreamResult,AnnualTaxDocumentData} */
    public function handle(RetryAnnualTaxDownstreamCommand $command): array
    {
        return $this->service->retry($command->companyId, $command->reconciliationId, $command->actorId);
    }
}
