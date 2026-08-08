<?php

namespace Modules\Notification\Application\Queries\NotificationInbox;

final readonly class NotificationInboxQuery
{
    public function __construct(public int $companyId, public int $userId, public ?string $filter, public ?string $category, public int $page = 1, public int $perPage = 20) {}
}
