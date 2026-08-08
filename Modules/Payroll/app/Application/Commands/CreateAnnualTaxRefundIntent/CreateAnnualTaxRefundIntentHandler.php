<?php

namespace Modules\Payroll\Application\Commands\CreateAnnualTaxRefundIntent;

use Modules\Payroll\Application\DTOs\AnnualTaxDownstreamResult;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxDeliveryService;

final readonly class CreateAnnualTaxRefundIntentHandler
{
    public function __construct(private PayrollAnnualTaxDeliveryService $service) {}

    public function handle(CreateAnnualTaxRefundIntentCommand $command): AnnualTaxDownstreamResult
    {
        return $this->service->refundIntent($command->companyId, $command->reconciliationId, $command->actorId);
    }
}
