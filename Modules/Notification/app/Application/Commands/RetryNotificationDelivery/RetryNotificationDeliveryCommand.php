<?php

namespace Modules\Notification\Application\Commands\RetryNotificationDelivery;

final readonly class RetryNotificationDeliveryCommand
{
    public function __construct(public int $companyId, public int $deliveryId) {}
}
