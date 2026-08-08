<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Support\Facades\DB;

final class HRGovernanceAuditService
{
    /** @param array<string, scalar|null> $metadata */
    public function append(int $companyId, int $actorId, string $entityType, int $entityId, string $action, string $key, array $metadata = []): void
    {
        $payload = compact('companyId', 'actorId', 'entityType', 'entityId', 'action', 'metadata');
        DB::table('hr_governance_audit_events')->insertOrIgnore([
            'company_id' => $companyId,
            'actor_id' => $actorId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            'idempotency_key' => $key,
            'created_at' => now(),
        ]);
    }
}
