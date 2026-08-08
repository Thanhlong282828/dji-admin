<?php

namespace Modules\Employee\Application\Commands\SendEmployeeUserInvitation;

final readonly class SendEmployeeUserInvitationCommand
{
    public function __construct(public int $companyId, public int $employeeId, public string $email, public int $actorId) {}
}
