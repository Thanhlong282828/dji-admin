<?php

namespace Modules\Employee\Application\Commands\TransitionEmploymentContract;

use Modules\Employee\Domain\Models\EmploymentContract;
use Modules\Employee\Domain\Services\EmploymentContractService;

final readonly class TransitionEmploymentContractHandler
{
    public function __construct(private EmploymentContractService $service) {}

    public function handle(TransitionEmploymentContractCommand $command): EmploymentContract
    {
        return $this->service->transition($command->companyId, $command->contractId, $command->target, $command->actorId, $command->reason);
    }
}
