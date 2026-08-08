<?php

namespace Modules\Employee\Application\Commands\RemoveEmployeeAvatar;

use Modules\Employee\Domain\Services\EmployeeAvatarService;

final readonly class RemoveEmployeeAvatarHandler
{
    public function __construct(private EmployeeAvatarService $service) {}

    public function handle(RemoveEmployeeAvatarCommand $command): void
    {
        $this->service->remove($command->companyId, $command->employeeId);
    }
}
