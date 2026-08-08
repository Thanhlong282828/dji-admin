<?php

namespace Modules\Payroll\Application\Commands\SaveEmployeeInsuranceProfile;

final readonly class SaveEmployeeInsuranceProfileCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $employeeId, public ?int $profileId, public array $data, public int $actorId) {}
}
