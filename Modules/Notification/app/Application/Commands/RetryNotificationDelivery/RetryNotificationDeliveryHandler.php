<?php

namespace Modules\Notification\Application\Commands\RetryNotificationDelivery;

use Modules\Notification\Domain\Services\NotificationDeliveryService;

final readonly class RetryNotificationDeliveryHandler
{
    public function __construct(private NotificationDeliveryService $service) {}

    public function handle(RetryNotificationDeliveryCommand $command): void
    {
        $this->service->retry($command->companyId, $command->deliveryId);
    }
}
