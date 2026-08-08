<?php

namespace Modules\Workflow\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WorkflowNotificationOutboxService
{
    /** @param array<string, scalar|null> $payload */
    public function emit(int $companyId, int $instanceId, string $eventType, array $payload): void
    {
        $safe = ['instance_id' => $instanceId] + $payload;
        $json = json_encode($safe, JSON_THROW_ON_ERROR);
        $checksum = hash('sha256', $json);
        if (DB::table('workflow_outbox')->where('company_id', $companyId)->where('workflow_instance_id', $instanceId)->where('event_type', $eventType)->where('payload_checksum', $checksum)->exists()) {
            return;
        }
        DB::table('workflow_outbox')->insert(['event_id' => (string) Str::uuid(), 'company_id' => $companyId, 'workflow_instance_id' => $instanceId, 'event_type' => $eventType, 'payload' => $json, 'payload_checksum' => $checksum, 'occurred_at' => now(), 'created_at' => now()]);
    }
}
