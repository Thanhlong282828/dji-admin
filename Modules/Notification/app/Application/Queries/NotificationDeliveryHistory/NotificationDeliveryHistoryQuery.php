<?php

namespace Modules\Notification\Application\Queries\NotificationDeliveryHistory;

final readonly class NotificationDeliveryHistoryQuery
{
    public function __construct(public int $companyId) {}
}
