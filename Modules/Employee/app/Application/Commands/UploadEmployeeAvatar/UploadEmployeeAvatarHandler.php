<?php

namespace Modules\Employee\Application\Commands\UploadEmployeeAvatar;

use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\EmployeeAvatarService;

final readonly class UploadEmployeeAvatarHandler
{
    public function __construct(private EmployeeAvatarService $service) {}

    public function handle(UploadEmployeeAvatarCommand $command): Employee
    {
        return $this->service->upload($command->companyId, $command->employeeId, $command->file);
    }
}
