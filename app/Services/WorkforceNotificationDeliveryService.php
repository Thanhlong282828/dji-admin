<?php

namespace App\Services;

use App\Contracts\WorkforceNotificationChannelSenderInterface;
use App\Models\User;
use App\Notifications\WorkforceNotification;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Throwable;

final readonly class WorkforceNotificationDeliveryService
{
    public function __construct(private ConnectionInterface $db, private WorkforceNotificationChannelSenderInterface $sender) {}

    /** @param array{company_id:int,entity_type:string,entity_id:int,employee_id:int|null,action:string,safe_title:string,safe_message:string,version:int,timestamp:string,url:string|null} $payload */
    public function deliver(User $recipient, string $type, array $payload): void
    {
        foreach (['database', 'broadcast'] as $channel) {
            $this->channel($recipient, $type, $channel, $payload);
        }
    }

    /** @param array{company_id:int,entity_type:string,entity_id:int,employee_id:int|null,action:string,safe_title:string,safe_message:string,version:int,timestamp:string,url:string|null} $payload */
    private function channel(User $recipient, string $type, string $channel, array $payload): void
    {
        $unique = ['company_id' => $payload['company_id'], 'notification_type' => $type, 'entity_type' => $payload['entity_type'], 'entity_id' => $payload['entity_id'], 'entity_version' => $payload['version'], 'recipient_id' => $recipient->id, 'channel' => $channel, 'action' => $payload['action']];
        $inserted = $this->db->table('notification_deliveries')->insertOrIgnore($unique + ['idempotency_key' => (string) Str::uuid(), 'status' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $marker = $this->db->table('notification_deliveries')->where($unique)->first();
        if ($marker === null) {
            return;
        } $status = is_string($marker->status ?? null) ? $marker->status : '';
        if ($inserted === 0 && $status !== 'failed') {
            return;
        }
        $id = is_numeric($marker->id ?? null) ? (int) $marker->id : 0;
        try {
            $this->sender->send($recipient, new WorkforceNotification($payload), $channel);
            $this->db->table('notification_deliveries')->where('id', $id)->update(['status' => 'sent', 'attempts' => $this->db->raw('attempts + 1'), 'sent_at' => now(), 'failed_at' => null, 'failure_message' => null, 'updated_at' => now()]);
        } catch (Throwable $e) {
            $this->db->table('notification_deliveries')->where('id', $id)->update(['status' => 'failed', 'attempts' => $this->db->raw('attempts + 1'), 'failed_at' => now(), 'failure_message' => Str::limit($e->getMessage(), 500), 'updated_at' => now()]);
            activity('workforce-notification')->event('failed')->withProperties(['company_id' => $payload['company_id'], 'entity_type' => $payload['entity_type'], 'entity_id' => $payload['entity_id'], 'recipient_id' => $recipient->id, 'channel' => $channel])->log('Workforce notification delivery failed');
        }
    }
}
