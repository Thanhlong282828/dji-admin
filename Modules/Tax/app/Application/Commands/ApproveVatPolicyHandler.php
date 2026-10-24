<?php

namespace Modules\Tax\Application\Commands;

use Modules\Tax\Application\Services\VatPolicyService;

final readonly class ApproveVatPolicyHandler
{
    public function __construct(private VatPolicyService $service) {}

    public function handle(ApproveVatPolicyCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->policyId, $command->actor);
    }
}
