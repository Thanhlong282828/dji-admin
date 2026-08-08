<?php

namespace Modules\Notification\Infrastructure\Channels;

use App\Models\User;
use Modules\Notification\Domain\Contracts\NotificationChannelSenderInterface;
use Modules\Notification\Domain\Events\NotificationMessageBroadcast;

final class BroadcastNotificationSender implements NotificationChannelSenderInterface
{
    public function channel(): string
    {
        return 'broadcast';
    }

    public function send(string $artifactUuid, User $recipient, array $message): void
    {
        event(new NotificationMessageBroadcast((int) $recipient->id, $message));
    }
}
