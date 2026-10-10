<?php

namespace Modules\Tax\Application\Commands;

use Modules\Tax\Application\Services\VatPolicyService;

final readonly class UpdateVatPolicyDraftHandler
{
    public function __construct(private VatPolicyService $service) {}

    public function handle(UpdateVatPolicyDraftCommand $command): int
    {
        return $this->service->updateDraft($command->companyId, $command->policyId, $command->actor, $command->data);
    }
}
