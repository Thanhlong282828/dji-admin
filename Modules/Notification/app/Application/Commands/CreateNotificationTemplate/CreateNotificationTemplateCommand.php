<?php

namespace Modules\Notification\Application\Commands\CreateNotificationTemplate;

final readonly class CreateNotificationTemplateCommand
{
    public function __construct(public int $companyId, public int $actorId, public string $code, public string $name, public string $category, public string $subject, public string $body, public bool $mandatory) {}
}
