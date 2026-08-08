<?php

namespace Modules\Payroll\Application\Commands\SaveEmployeeInsuranceProfile;

use Modules\Payroll\Domain\Services\InsuranceManagementService;

final readonly class SaveEmployeeInsuranceProfileHandler
{
    public function __construct(private InsuranceManagementService $service) {}

    public function handle(SaveEmployeeInsuranceProfileCommand $command): int
    {
        return $this->service->saveProfile($command->companyId, $command->employeeId, $command->profileId, $command->data, $command->actorId);
    }
}
