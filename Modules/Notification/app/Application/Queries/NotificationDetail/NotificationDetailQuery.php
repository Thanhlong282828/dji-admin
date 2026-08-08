<?php

namespace Modules\Notification\Application\Queries\NotificationDetail;

final readonly class NotificationDetailQuery
{
    public function __construct(public int $companyId, public int $userId, public int $messageId) {}
}
