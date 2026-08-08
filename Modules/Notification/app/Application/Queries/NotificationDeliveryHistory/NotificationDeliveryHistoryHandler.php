<?php

namespace Modules\Notification\Application\Queries\NotificationDeliveryHistory;

use Modules\Notification\Application\DTOs\NotificationCenterViewModel;
use Modules\Notification\Infrastructure\Persistence\NotificationReadRepository;

final readonly class NotificationDeliveryHistoryHandler
{
    public function __construct(private NotificationReadRepository $repository) {}

    public function handle(NotificationDeliveryHistoryQuery $q): NotificationCenterViewModel
    {
        return $this->repository->deliveries($q->companyId);
    }
}
