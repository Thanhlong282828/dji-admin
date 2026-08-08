<?php

namespace Modules\Payroll\Application\Commands\CreateAnnualTaxPaymentIntent;

use Modules\Payroll\Application\DTOs\AnnualTaxDownstreamResult;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxDeliveryService;

final readonly class CreateAnnualTaxPaymentIntentHandler
{
    public function __construct(private PayrollAnnualTaxDeliveryService $service) {}

    public function handle(CreateAnnualTaxPaymentIntentCommand $command): AnnualTaxDownstreamResult
    {
        return $this->service->paymentIntent($command->companyId, $command->reconciliationId, $command->actorId);
    }
}
