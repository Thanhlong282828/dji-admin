<?php

namespace Modules\Audit\Infrastructure\Ingestion;

use Illuminate\Database\Query\Builder;
use Modules\Audit\Application\DTOs\SourceAuditEnvelope;
use stdClass;

final readonly class IntegrationAuditConsumer extends AbstractSourceAuditConsumer
{
    protected function sourceQuery(): Builder
    {
        return $this->db->table('integration_outbox');
    }

    protected function consumerName(): string
    {
        return 'enterprise-audit-integration-v1';
    }

    protected function envelope(stdClass $row): SourceAuditEnvelope
    {
        $v = get_object_vars($row);
        $payload = $this->json($v['payload'] ?? null);
        $eventId = $this->text($v['uuid'] ?? $v['id'] ?? null);

        return new SourceAuditEnvelope($eventId, $this->integer($v['company_id'] ?? null) ?? 0, 'Integration', $this->text($v['event_type'] ?? null, 'integration.changed'), $this->text($v['aggregate_type'] ?? null, 'integration_resource'), $this->text($v['aggregate_id'] ?? null), 'user', $this->integer($payload['actor_id'] ?? null), [], $payload, [], $this->text($payload['correlation_id'] ?? null, $eventId), $eventId, $this->date($v['created_at'] ?? null));
    }
}
