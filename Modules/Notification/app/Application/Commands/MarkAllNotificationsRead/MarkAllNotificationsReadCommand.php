<?php

namespace Modules\Notification\Application\Commands\MarkAllNotificationsRead;

final readonly class MarkAllNotificationsReadCommand
{
    public function __construct(public int $companyId, public int $userId) {}
}
