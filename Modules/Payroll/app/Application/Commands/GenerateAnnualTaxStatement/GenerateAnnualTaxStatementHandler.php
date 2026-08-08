<?php

namespace Modules\Payroll\Application\Commands\GenerateAnnualTaxStatement;

use Modules\Payroll\Application\DTOs\AnnualTaxDocumentData;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxDeliveryService;

final readonly class GenerateAnnualTaxStatementHandler
{
    public function __construct(private PayrollAnnualTaxDeliveryService $service) {}

    public function handle(GenerateAnnualTaxStatementCommand $command): AnnualTaxDocumentData
    {
        return $this->service->generateStatement($command->companyId, $command->reconciliationId, $command->actorId);
    }
}
