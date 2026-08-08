<?php

namespace Modules\Notification\Application\Commands\PublishNotificationTemplate;

use Modules\Notification\Domain\Services\NotificationTemplateService;

final readonly class PublishNotificationTemplateHandler
{
    public function __construct(private NotificationTemplateService $service) {}

    public function handle(PublishNotificationTemplateCommand $c): void
    {
        $this->service->publish($c->companyId, $c->templateId, $c->actorId);
    }
}
