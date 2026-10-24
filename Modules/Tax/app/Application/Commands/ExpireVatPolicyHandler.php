<?php

namespace Modules\Tax\Application\Commands;

use Modules\Tax\Application\Services\VatPolicyService;

final readonly class ExpireVatPolicyHandler
{
    public function __construct(private VatPolicyService $service) {}

    public function handle(ExpireVatPolicyCommand $command): int
    {
        return $this->service->expire($command->companyId, $command->policyId, $command->actor);
    }
}
