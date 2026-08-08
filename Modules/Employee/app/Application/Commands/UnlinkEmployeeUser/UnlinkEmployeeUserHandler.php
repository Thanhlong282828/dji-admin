<?php

namespace Modules\Employee\Application\Commands\UnlinkEmployeeUser;

use Modules\Employee\Domain\Services\EmployeeUserLinkService;

final readonly class UnlinkEmployeeUserHandler
{
    public function __construct(private EmployeeUserLinkService $service) {}

    public function handle(UnlinkEmployeeUserCommand $command): void
    {
        $this->service->unlink($command->companyId, $command->employeeId);
    }
}
