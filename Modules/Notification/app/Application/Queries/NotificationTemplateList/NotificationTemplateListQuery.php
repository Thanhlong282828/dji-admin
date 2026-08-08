<?php

namespace Modules\Notification\Application\Queries\NotificationTemplateList;

final readonly class NotificationTemplateListQuery
{
    public function __construct(public int $companyId) {}
}
