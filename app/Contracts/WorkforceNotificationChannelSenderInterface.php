<?php

namespace App\Contracts;

use App\Models\User;
use App\Notifications\WorkforceNotification;

interface WorkforceNotificationChannelSenderInterface
{
    public function send(User $recipient, WorkforceNotification $notification, string $channel): void;
}
