<?php

namespace Modules\Notification\Application\Commands\QueueNotification;

use Modules\Notification\Domain\Services\NotificationDeliveryService;

final readonly class QueueNotificationHandler
{
    public function __construct(private NotificationDeliveryService $service) {}

    public function handle(QueueNotificationCommand $command): int
    {
        return $this->service->queue($command->data);
    }
}
