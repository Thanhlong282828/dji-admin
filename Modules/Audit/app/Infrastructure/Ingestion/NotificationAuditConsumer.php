<?php

namespace Modules\Audit\Infrastructure\Ingestion;

use Illuminate\Database\Query\Builder;
use Modules\Audit\Application\DTOs\SourceAuditEnvelope;
use stdClass;

final readonly class NotificationAuditConsumer extends AbstractSourceAuditConsumer
{
    protected function sourceQuery(): Builder
    {
        return $this->db->table('notification_outbox');
    }

    protected function consumerName(): string
    {
        return 'enterprise-audit-notification-v1';
    }

    protected function envelope(stdClass $row): SourceAuditEnvelope
    {
        $v = get_object_vars($row);
        $payload = $this->json($v['safe_payload'] ?? null);
        $eventId = $this->text($v['event_id'] ?? $v['id'] ?? null);

        return new SourceAuditEnvelope($eventId, $this->integer($v['company_id'] ?? null) ?? 0, 'Notification', $this->text($v['event_type'] ?? null, 'notification.changed'), $this->text($payload['entity_type'] ?? null, 'notification_message'), $this->text($payload['entity_id'] ?? $payload['message_id'] ?? $v['id'] ?? null), 'system', $this->integer($payload['actor_id'] ?? null), [], $payload, ['delivery_safe' => true], $this->text($payload['correlation_id'] ?? null, $eventId), $eventId, $this->date($v['created_at'] ?? null));
    }
}
