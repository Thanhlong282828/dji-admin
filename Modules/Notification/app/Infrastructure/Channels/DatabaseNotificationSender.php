<?php

namespace Modules\Notification\Infrastructure\Channels;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Notification\Domain\Contracts\NotificationChannelSenderInterface;

final class DatabaseNotificationSender implements NotificationChannelSenderInterface
{
    public function channel(): string
    {
        return 'database';
    }

    public function send(string $artifactUuid, User $recipient, array $message): void
    {
        DB::table('notifications')->insertOrIgnore([
            'id' => $artifactUuid,
            'type' => 'enterprise-notification-center',
            'notifiable_type' => User::class,
            'notifiable_id' => $recipient->id,
            'data' => json_encode($message, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
