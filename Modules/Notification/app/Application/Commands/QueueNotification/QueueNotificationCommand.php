<?php

namespace Modules\Notification\Application\Commands\QueueNotification;

use Modules\Notification\Application\DTOs\QueueNotificationData;

final readonly class QueueNotificationCommand
{
    public function __construct(public QueueNotificationData $data) {}
}
