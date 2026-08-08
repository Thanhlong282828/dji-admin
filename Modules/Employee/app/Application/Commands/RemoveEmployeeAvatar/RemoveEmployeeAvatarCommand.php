<?php

namespace Modules\Employee\Application\Commands\RemoveEmployeeAvatar;

final readonly class RemoveEmployeeAvatarCommand
{
    public function __construct(public int $companyId, public int $employeeId, public int $actorId) {}
}
