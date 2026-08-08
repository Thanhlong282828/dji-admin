<?php

namespace Modules\Notification\Application\Queries\UnreadNotificationCount;

use Modules\Notification\Infrastructure\Persistence\NotificationReadRepository;

final readonly class UnreadNotificationCountHandler
{
    public function __construct(private NotificationReadRepository $repository) {}

    public function handle(UnreadNotificationCountQuery $q): int
    {
        return $this->repository->unread($q->companyId, $q->userId);
    }
}
