<?php

namespace Modules\Employee\Application\Commands\LinkEmployeeUser;

final readonly class LinkEmployeeUserCommand
{
    public function __construct(public int $companyId, public int $employeeId, public int $userId, public int $actorId) {}
}
