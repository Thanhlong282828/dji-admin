<?php

namespace Modules\Audit\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Audit\Domain\Support\AuditChecksum;

final readonly class AuditIntegrityService
{
    public function __construct(private ConnectionInterface $db) {}

    public function verify(int $companyId, int $eventId): bool
    {
        $event = $this->db->table('audit_events')->where(['company_id' => $companyId, 'id' => $eventId])->first();
        if ($event === null) {
            return false;
        }
        $eventValues = get_object_vars($event);
        $snapshots = $this->db->table('audit_event_snapshots')->where(['company_id' => $companyId, 'audit_event_id' => $eventId])->get();
        $payloads = [];
        foreach ($snapshots as $snapshot) {
            $values = get_object_vars($snapshot);
            $type = is_string($values['snapshot_type'] ?? null) ? $values['snapshot_type'] : '';
            $payload = $this->jsonObject($values['payload'] ?? null);
            $checksum = is_string($values['checksum'] ?? null) ? $values['checksum'] : '';
            if (! in_array($type, ['before', 'after'], true) || ! hash_equals($checksum, AuditChecksum::make($payload))) {
                return false;
            }
            $payloads[$type] = $payload;
        }
        if (! isset($payloads['before'], $payloads['after'])) {
            return false;
        }
        $metadata = $this->jsonObject($eventValues['metadata'] ?? null);
        $auditPayload = ['actor_type' => $eventValues['actor_type'] ?? null, 'actor_id' => $eventValues['actor_id'] ?? null, 'action' => $eventValues['action'] ?? null, 'source_module' => $eventValues['source_module'] ?? null, 'source_event' => $eventValues['source_event'] ?? null, 'resource_type' => $eventValues['resource_type'] ?? null, 'resource_id' => $eventValues['resource_id'] ?? null, 'before' => $payloads['before'], 'after' => $payloads['after'], 'metadata' => $metadata];
        $stored = is_string($eventValues['payload_checksum'] ?? null) ? $eventValues['payload_checksum'] : '';

        return hash_equals($stored, AuditChecksum::make($auditPayload));
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_filter($decoded, static fn (int|string $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
            }
        }

        return [];
    }
}
