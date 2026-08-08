<?php

namespace Modules\Notification\Application\Queries\NotificationInbox;

use Modules\Notification\Application\DTOs\NotificationCenterViewModel;
use Modules\Notification\Infrastructure\Persistence\NotificationReadRepository;

final readonly class NotificationInboxHandler
{
    public function __construct(private NotificationReadRepository $repository) {}

    public function handle(NotificationInboxQuery $q): NotificationCenterViewModel
    {
        return $this->repository->inbox($q->companyId, $q->userId, $q->filter, $q->category, $q->page, $q->perPage);
    }
}
