<?php

namespace Modules\Employee\Application\Commands\LinkEmployeeUser;

use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\EmployeeUserLinkService;

final readonly class LinkEmployeeUserHandler
{
    public function __construct(private EmployeeUserLinkService $service) {}

    public function handle(LinkEmployeeUserCommand $command): Employee
    {
        return $this->service->link($command->companyId, $command->employeeId, $command->userId);
    }
}
