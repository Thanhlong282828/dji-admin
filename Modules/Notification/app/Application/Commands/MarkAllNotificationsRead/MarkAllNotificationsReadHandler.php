<?php

namespace Modules\Notification\Application\Commands\MarkAllNotificationsRead;

use Modules\Notification\Domain\Services\NotificationReadStateService;

final readonly class MarkAllNotificationsReadHandler
{
    public function __construct(private NotificationReadStateService $service) {}

    public function handle(MarkAllNotificationsReadCommand $command): int
    {
        return $this->service->markAll($command->companyId, $command->userId);
    }
}
