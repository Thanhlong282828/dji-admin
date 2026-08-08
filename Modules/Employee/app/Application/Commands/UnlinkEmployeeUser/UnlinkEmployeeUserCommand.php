<?php

namespace Modules\Employee\Application\Commands\UnlinkEmployeeUser;

final readonly class UnlinkEmployeeUserCommand
{
    public function __construct(public int $companyId, public int $employeeId, public int $actorId) {}
}
