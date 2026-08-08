<?php

namespace Modules\Payroll\Application\Commands\SaveTaxPolicy;

use Modules\Payroll\Domain\Services\TaxManagementService;

final readonly class SaveTaxPolicyHandler
{
    public function __construct(private TaxManagementService $service) {}

    public function handle(SaveTaxPolicyCommand $command): int
    {
        return $this->service->savePolicy($command->companyId, $command->policyId, $command->data, $command->actorId);
    }
}
