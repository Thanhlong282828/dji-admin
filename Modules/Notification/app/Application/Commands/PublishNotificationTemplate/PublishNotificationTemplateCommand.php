<?php

namespace Modules\Notification\Application\Commands\PublishNotificationTemplate;

final readonly class PublishNotificationTemplateCommand
{
    public function __construct(public int $companyId, public int $templateId, public int $actorId) {}
}
