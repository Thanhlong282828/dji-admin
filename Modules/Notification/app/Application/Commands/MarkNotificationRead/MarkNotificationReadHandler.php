<?php

namespace Modules\Notification\Application\Commands\MarkNotificationRead;

use Modules\Notification\Domain\Services\NotificationReadStateService;

final readonly class MarkNotificationReadHandler
{
    public function __construct(private NotificationReadStateService $service) {}

    public function handle(MarkNotificationReadCommand $command): void
    {
        $this->service->mark($command->companyId, $command->userId, $command->messageId);
    }
}
