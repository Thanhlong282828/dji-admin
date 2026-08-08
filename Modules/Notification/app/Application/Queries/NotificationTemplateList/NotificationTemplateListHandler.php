<?php

namespace Modules\Notification\Application\Queries\NotificationTemplateList;

use Modules\Notification\Application\DTOs\NotificationCenterViewModel;
use Modules\Notification\Infrastructure\Persistence\NotificationReadRepository;

final readonly class NotificationTemplateListHandler
{
    public function __construct(private NotificationReadRepository $repository) {}

    public function handle(NotificationTemplateListQuery $q): NotificationCenterViewModel
    {
        return $this->repository->templates($q->companyId);
    }
}
