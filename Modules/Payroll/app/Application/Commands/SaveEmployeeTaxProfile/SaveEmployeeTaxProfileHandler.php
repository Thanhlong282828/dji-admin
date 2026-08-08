<?php

namespace Modules\Payroll\Application\Commands\SaveEmployeeTaxProfile;

use Modules\Payroll\Domain\Services\TaxManagementService;

final readonly class SaveEmployeeTaxProfileHandler
{
    public function __construct(private TaxManagementService $service) {}

    public function handle(SaveEmployeeTaxProfileCommand $command): int
    {
        return $this->service->saveProfile($command->companyId, $command->employeeId, $command->profileId, $command->data, $command->actorId);
    }
}
