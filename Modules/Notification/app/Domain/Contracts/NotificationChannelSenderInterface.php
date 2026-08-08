<?php

namespace Modules\Notification\Domain\Contracts;

use App\Models\User;

interface NotificationChannelSenderInterface
{
    public function channel(): string;

    /** @param array{uuid:string,title:string,body:string,category:string,entity_type:string,entity_id:int,action:string,navigation_reference:string|null,checksum:string} $message */
    public function send(string $artifactUuid, User $recipient, array $message): void;
}
