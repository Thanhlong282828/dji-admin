<?php

namespace Modules\Notification\Application\Queries\UnreadNotificationCount;

final readonly class UnreadNotificationCountQuery
{
    public function __construct(public int $companyId, public int $userId) {}
}
