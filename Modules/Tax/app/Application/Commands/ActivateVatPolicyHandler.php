<?php

namespace Modules\Tax\Application\Commands;

use Modules\Tax\Application\Services\VatPolicyService;

final readonly class ActivateVatPolicyHandler
{
    public function __construct(private VatPolicyService $service) {}

    public function handle(ActivateVatPolicyCommand $command): int
    {
        return $this->service->activate($command->companyId, $command->policyId, $command->actor);
    }
}
