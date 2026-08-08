<?php

namespace Modules\Notification\Infrastructure\Channels;

use App\Models\User;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Modules\Notification\Domain\Contracts\NotificationChannelSenderInterface;

final class MailNotificationSender implements NotificationChannelSenderInterface
{
    public function channel(): string
    {
        return 'email';
    }

    public function send(string $artifactUuid, User $recipient, array $message): void
    {
        Mail::raw($message['body'], fn (Message $mail): Message => $mail->to((string) $recipient->email)->subject($message['title']));
    }
}
