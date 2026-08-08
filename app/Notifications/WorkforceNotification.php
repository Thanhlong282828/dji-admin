<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class WorkforceNotification extends Notification
{
    use Queueable;

    /** @param array{company_id:int,entity_type:string,entity_id:int,employee_id:int|null,action:string,safe_title:string,safe_message:string,version:int,timestamp:string,url:string|null} $data */
    public function __construct(private readonly array $data) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array{company_id:int,entity_type:string,entity_id:int,employee_id:int|null,action:string,safe_title:string,safe_message:string,version:int,timestamp:string,url:string|null} */
    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->data);
    }
}
