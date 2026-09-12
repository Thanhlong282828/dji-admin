<?php

namespace Modules\Workflow\Infrastructure\Callbacks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Workflow\Domain\Contracts\WorkflowCompletionHandlerInterface;
use Modules\Workflow\Domain\Events\WorkflowCompleted;

final class OutboxWorkflowCompletionHandler implements WorkflowCompletionHandlerInterface
{
    public function completed(int $companyId, int $instanceId, string $subjectType, int $subjectId): void
    {
        if (! DB::table('workflow_outbox')->where('company_id', $companyId)->where('workflow_instance_id', $instanceId)->where('event_type', 'workflow.completed')->exists()) {
            $payload = ['instance_id' => $instanceId, 'subject_type' => $subjectType, 'subject_id' => $subjectId];
            DB::table('workflow_outbox')->insertOrIgnore(['event_id' => (string) Str::uuid(), 'company_id' => $companyId, 'workflow_instance_id' => $instanceId, 'event_type' => 'workflow.completed', 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'occurred_at' => now(), 'created_at' => now()]);
        }
        event(new WorkflowCompleted($companyId, $instanceId, $subjectType, $subjectId));
    }
}
