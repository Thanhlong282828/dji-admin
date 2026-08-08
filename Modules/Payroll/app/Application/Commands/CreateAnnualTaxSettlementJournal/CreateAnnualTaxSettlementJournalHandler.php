<?php

namespace Modules\Payroll\Application\Commands\CreateAnnualTaxSettlementJournal;

use Modules\Payroll\Application\DTOs\AnnualTaxDownstreamResult;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxDeliveryService;

final readonly class CreateAnnualTaxSettlementJournalHandler
{
    public function __construct(private PayrollAnnualTaxDeliveryService $service) {}

    public function handle(CreateAnnualTaxSettlementJournalCommand $command): AnnualTaxDownstreamResult
    {
        return $this->service->settlementJournal($command->companyId, $command->reconciliationId, $command->actorId);
    }
}
