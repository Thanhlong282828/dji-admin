<?php

namespace Modules\Employee\Application\Commands\CreateEmploymentContract;

use Modules\Employee\Domain\Models\EmploymentContract;
use Modules\Employee\Domain\Services\EmploymentContractService;

final readonly class CreateEmploymentContractHandler
{
    public function __construct(private EmploymentContractService $service) {}

    public function handle(CreateEmploymentContractCommand $command): EmploymentContract
    {
        return $this->service->create($command->companyId, $command->employeeId, $command->data, $command->actorId);
    }
}
