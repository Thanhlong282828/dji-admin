<?php

namespace Modules\Payroll\Application\Commands\CollectAnnualTaxSources;

use Modules\Payroll\Application\DTOs\AnnualTaxSourceCollectionResult;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;

final readonly class CollectAnnualTaxSourcesHandler
{
    public function __construct(private PayrollAnnualTaxReconciliationService $service) {}

    public function handle(CollectAnnualTaxSourcesCommand $command): AnnualTaxSourceCollectionResult
    {
        return $this->service->collect($command->companyId, $command->reconciliationId, $command->actorId);
    }
}
