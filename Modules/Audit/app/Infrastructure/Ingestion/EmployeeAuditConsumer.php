<?php

namespace Modules\Audit\Infrastructure\Ingestion;

use Illuminate\Database\Query\Builder;
use Modules\Audit\Application\DTOs\SourceAuditEnvelope;
use stdClass;

final readonly class EmployeeAuditConsumer extends AbstractSourceAuditConsumer
{
    protected function sourceQuery(): Builder
    {
        return $this->db->table('employee_lifecycle_outbox');
    }

    protected function consumerName(): string
    {
        return 'enterprise-audit-employee-v1';
    }

    protected function envelope(stdClass $row): SourceAuditEnvelope
    {
        $v = get_object_vars($row);
        $payload = $this->json($v['payload'] ?? null);
        $eventId = $this->text($v['event_id'] ?? $v['id'] ?? null);

        return new SourceAuditEnvelope($eventId, $this->integer($v['company_id'] ?? null) ?? 0, 'Employee', $this->text($v['event_type'] ?? null, 'employee.changed'), 'employee', $this->text($v['employee_id'] ?? null), 'user', $this->integer($payload['actor_id'] ?? $payload['created_by'] ?? null), [], $payload, [], $this->text($payload['correlation_id'] ?? null, $eventId), $eventId, $this->date($v['occurred_at'] ?? $v['created_at'] ?? null));
    }
}
