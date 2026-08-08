<?php

namespace Modules\Notification\Application\Commands\MarkNotificationRead;

final readonly class MarkNotificationReadCommand
{
    public function __construct(public int $companyId, public int $userId, public int $messageId) {}
}
