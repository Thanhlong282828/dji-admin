<?php

namespace Modules\Notification\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final readonly class NotificationMessageBroadcast implements ShouldBroadcastNow
{
    /** @param array{uuid:string,title:string,body:string,category:string,entity_type:string,entity_id:int,action:string,navigation_reference:string|null,checksum:string} $message */
    public function __construct(public int $userId, public array $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'notification.center.updated';
    }

    /** @return array{message:array{uuid:string,title:string,body:string,category:string,entity_type:string,entity_id:int,action:string,navigation_reference:string|null,checksum:string}} */
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
