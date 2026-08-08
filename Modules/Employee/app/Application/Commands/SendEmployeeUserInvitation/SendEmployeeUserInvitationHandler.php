<?php

namespace Modules\Employee\Application\Commands\SendEmployeeUserInvitation;

use Illuminate\Support\Facades\Notification;
use Modules\Employee\Domain\Services\EmployeeUserLinkService;
use Modules\Employee\Notifications\EmployeeUserInvitationNotification;

final readonly class SendEmployeeUserInvitationHandler
{
    public function __construct(private EmployeeUserLinkService $service) {}

    public function handle(SendEmployeeUserInvitationCommand $command): void
    {
        $token = $this->service->invite($command->companyId, $command->employeeId, $command->email, $command->actorId);
        Notification::route('mail', $command->email)->notify(new EmployeeUserInvitationNotification($token, $command->employeeId));
    }
}
