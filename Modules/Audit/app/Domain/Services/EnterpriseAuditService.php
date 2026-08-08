<?php

namespace Modules\Audit\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Audit\Domain\Support\AuditChecksum;

final readonly class EnterpriseAuditService implements AuditRecorderInterface
{
    public function __construct(private ConnectionInterface $db, private AuditRedactionService $redaction, private AuditDiffService $diff) {}

    public function record(AuditEventData $data): int
    {
        return $this->db->transaction(function () use ($data): int {
            $this->db->table('companies')->where('id', $data->companyId)->lockForUpdate()->first();
            $existing = $this->db->table('audit_events')->where(['company_id' => $data->companyId, 'idempotency_key' => $data->idempotencyKey])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }

            $before = $this->redaction->redact($data->before);
            $after = $this->redaction->redact($data->after);
            $metadata = $this->redaction->redact($data->metadata) + ['diff' => $this->diff->diff($before, $after)];
            $correlation = $this->correlation($data);
            $payload = ['actor_type' => $data->actorType, 'actor_id' => $data->actorId, 'action' => $data->action, 'source_module' => $data->sourceModule, 'source_event' => $data->sourceEvent, 'resource_type' => $data->resourceType, 'resource_id' => $data->resourceId, 'before' => $before, 'after' => $after, 'metadata' => $metadata];
            $id = (int) $this->db->table('audit_events')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $data->companyId, 'correlation_id' => $correlation, 'causation_id' => $data->causationId, 'idempotency_key' => $data->idempotencyKey, 'actor_type' => $data->actorType, 'actor_id' => $data->actorId, 'action' => $data->action, 'source_module' => $data->sourceModule, 'source_event' => $data->sourceEvent, 'resource_type' => $data->resourceType, 'resource_id' => $data->resourceId, 'ip_hash' => $data->ip ? hash('sha256', $data->ip) : null, 'user_agent_hash' => $data->userAgent ? hash('sha256', $data->userAgent) : null, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'payload_checksum' => AuditChecksum::make($payload), 'occurred_at' => $data->occurredAt ?? now(), 'created_at' => now()]);
            foreach (['before' => $before, 'after' => $after] as $type => $snapshot) {
                $this->db->table('audit_event_snapshots')->insert(['company_id' => $data->companyId, 'audit_event_id' => $id, 'snapshot_type' => $type, 'payload' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'checksum' => AuditChecksum::make($snapshot), 'created_at' => now()]);
            }

            $out = ['audit_event_id' => $id, 'action' => $data->action, 'resource_type' => $data->resourceType, 'resource_id' => $data->resourceId];
            $this->db->table('audit_outbox')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $data->companyId, 'event_type' => 'audit.event.recorded', 'audit_event_id' => $id, 'payload' => json_encode($out, JSON_THROW_ON_ERROR), 'checksum' => AuditChecksum::make($out), 'idempotency_key' => 'event:'.$data->companyId.':'.$data->idempotencyKey, 'available_at' => now(), 'created_at' => now()]);

            return $id;
        }, 5);
    }

    private function correlation(AuditEventData $data): ?int
    {
        if ($data->correlationUuid === null) {
            return null;
        }

        $existing = $this->db->table('audit_correlations')->where(['company_id' => $data->companyId, 'uuid' => $data->correlationUuid])->value('id');
        if (is_numeric($existing)) {
            return (int) $existing;
        }

        return (int) $this->db->table('audit_correlations')->insertGetId(['uuid' => $data->correlationUuid, 'company_id' => $data->companyId, 'root_source' => $data->sourceModule, 'checksum' => AuditChecksum::make(['uuid' => $data->correlationUuid, 'source' => $data->sourceModule]), 'started_at' => $data->occurredAt ?? now()]);
    }
}
