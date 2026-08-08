<?php

namespace Modules\Workflow\Domain\Services;

use Illuminate\Support\Facades\DB;

final class WorkflowHistoryService
{
    /** @param array<string,scalar|null> $metadata */
    public function append(int $companyId, int $instanceId, ?int $taskId, ?int $actorId, string $event, ?string $from, ?string $to, string $key, array $metadata = []): void
    {
        $payload = compact('companyId', 'instanceId', 'taskId', 'actorId', 'event', 'from', 'to', 'metadata');
        DB::table('workflow_histories')->insertOrIgnore(['company_id' => $companyId, 'workflow_instance_id' => $instanceId, 'workflow_task_id' => $taskId, 'actor_id' => $actorId, 'event' => $event, 'from_status' => $from, 'to_status' => $to, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'idempotency_key' => $key, 'created_at' => now()]);
    }
}
