<?php

namespace Modules\Audit\Infrastructure\Ingestion;

use Illuminate\Database\Query\Builder;
use Modules\Audit\Application\DTOs\SourceAuditEnvelope;
use stdClass;

final readonly class WorkflowAuditConsumer extends AbstractSourceAuditConsumer
{
    protected function sourceQuery(): Builder
    {
        return $this->db->table('workflow_outbox');
    }

    protected function consumerName(): string
    {
        return 'enterprise-audit-workflow-v1';
    }

    protected function envelope(stdClass $row): SourceAuditEnvelope
    {
        $v = get_object_vars($row);
        $payload = $this->json($v['payload'] ?? null);
        $eventId = $this->text($v['event_id'] ?? $v['id'] ?? null);

        return new SourceAuditEnvelope($eventId, $this->integer($v['company_id'] ?? null) ?? 0, 'Workflow', $this->text($v['event_type'] ?? null, 'workflow.changed'), 'workflow_instance', $this->text($v['workflow_instance_id'] ?? null), 'user', $this->integer($payload['actor_id'] ?? $payload['decided_by'] ?? null), [], $payload, [], $this->text($payload['correlation_id'] ?? null, $eventId), $eventId, $this->date($v['occurred_at'] ?? $v['created_at'] ?? null));
    }
}
