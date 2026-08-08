<?php

namespace Modules\Notification\Application\Queries\NotificationDetail;

use Modules\Notification\Application\DTOs\NotificationCenterViewModel;
use Modules\Notification\Infrastructure\Persistence\NotificationReadRepository;

final readonly class NotificationDetailHandler
{
    public function __construct(private NotificationReadRepository $repository) {}

    public function handle(NotificationDetailQuery $q): NotificationCenterViewModel
    {
        return $this->repository->detail($q->companyId, $q->userId, $q->messageId);
    }
}
