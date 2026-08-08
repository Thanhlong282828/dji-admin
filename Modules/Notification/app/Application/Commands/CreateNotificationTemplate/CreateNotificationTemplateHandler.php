<?php

namespace Modules\Notification\Application\Commands\CreateNotificationTemplate;

use Modules\Notification\Domain\Services\NotificationTemplateService;

final readonly class CreateNotificationTemplateHandler
{
    public function __construct(private NotificationTemplateService $service) {}

    public function handle(CreateNotificationTemplateCommand $c): int
    {
        return $this->service->create($c->companyId, $c->actorId, $c->code, $c->name, $c->category, $c->subject, $c->body, $c->mandatory);
    }
}
