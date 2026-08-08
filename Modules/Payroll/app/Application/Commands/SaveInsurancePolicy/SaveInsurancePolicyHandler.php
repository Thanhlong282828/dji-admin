<?php

namespace Modules\Payroll\Application\Commands\SaveInsurancePolicy;

use Modules\Payroll\Domain\Services\InsuranceManagementService;

final readonly class SaveInsurancePolicyHandler
{
    public function __construct(private InsuranceManagementService $service) {}

    public function handle(SaveInsurancePolicyCommand $command): int
    {
        return $this->service->savePolicy($command->companyId, $command->policyId, $command->data, $command->actorId);
    }
}
