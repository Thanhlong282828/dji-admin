<?php

namespace Modules\Tax\Application\Commands;

use Modules\Tax\Application\Services\VatPolicyService;

final readonly class CreateVatPolicyHandler
{
    public function __construct(private VatPolicyService $service) {}

    public function handle(CreateVatPolicyCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
