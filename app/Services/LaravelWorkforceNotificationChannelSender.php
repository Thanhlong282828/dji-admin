<?php

namespace App\Services;

use App\Contracts\WorkforceNotificationChannelSenderInterface;
use App\Models\User;
use App\Notifications\WorkforceNotification;

final class LaravelWorkforceNotificationChannelSender implements WorkforceNotificationChannelSenderInterface
{
    public function send(User $recipient, WorkforceNotification $notification, string $channel): void
    {
        $recipient->notifyNow($notification, [$channel]);
    }
}
