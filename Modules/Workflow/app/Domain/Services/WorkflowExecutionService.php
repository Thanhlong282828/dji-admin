<?php

namespace Modules\Workflow\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Domain\Contracts\WorkflowActorResolverInterface;
use Modules\Workflow\Domain\Models\WorkflowDefinition;
use Modules\Workflow\Domain\Models\WorkflowDefinitionVersion;
use Modules\Workflow\Domain\Models\WorkflowInstance;
use Modules\Workflow\Domain\Models\WorkflowStep;

final readonly class WorkflowExecutionService
{
    public function __construct(private WorkflowActorResolverInterface $resolver, private WorkflowRoutingService $routing, private WorkflowHistoryService $history, private WorkflowNotificationOutboxService $notifications) {}

    public function start(StartWorkflowData $data): int
    {
        return DB::transaction(function () use ($data): int {
            $existing = DB::table('workflow_instances')->where('company_id', $data->companyId)->where('idempotency_key', $data->idempotencyKey)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $definition = WorkflowDefinition::query()->where('company_id', $data->companyId)->whereKey($data->definitionId)->where('status', 'published')->lockForUpdate()->first();
            abort_if($definition === null, 404);
            $version = WorkflowDefinitionVersion::query()->where('workflow_definition_id', $data->definitionId)->where('version', $definition->latest_version)->first();
            abort_if($version === null, 422);
            $snapshot = ['definition_id' => $data->definitionId, 'version_id' => (int) $version->id, 'version' => (int) $version->version, 'checksum' => (string) $version->checksum, 'context' => $data->context];
            $instanceId = (int) DB::table('workflow_instances')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $data->companyId, 'workflow_definition_version_id' => $version->id, 'workflow_type' => (string) $definition->code, 'subject_type' => $data->subjectType, 'subject_id' => $data->subjectId, 'definition_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'current_step' => 1, 'status' => 'pending', 'engine_status' => 'running', 'lock_version' => 1, 'idempotency_key' => $data->idempotencyKey, 'checksum' => hash('sha256', json_encode([$snapshot, $data->subjectType, $data->subjectId, $data->context], JSON_THROW_ON_ERROR)), 'started_by' => $data->actorId, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $steps = WorkflowStep::query()->where('workflow_definition_version_id', $version->id)->orderBy('sequence')->get();
            $routingType = $version->routing_type;
            $taskCount = 0;
            foreach ($steps as $step) {
                if ($routingType === 'sequential' && (int) $step->sequence !== 1) {
                    continue;
                }
                $condition = $this->condition($step->condition);
                if (! $this->routing->matches($data->context, $condition)) {
                    continue;
                }
                $taskCount += $this->createTasks($data, $instanceId, $step);
            }
            if ($taskCount === 0) {
                throw ValidationException::withMessages(['assignee' => 'Workflow has no eligible assignee for the persisted context.']);
            }
            $this->history->append($data->companyId, $instanceId, null, $data->actorId, 'workflow.started', 'draft', 'running', $data->idempotencyKey.'-history', ['definition_version_id' => (int) $version->id]);

            return $instanceId;
        }, 3);
    }

    public function createTasks(StartWorkflowData $data, int $instanceId, WorkflowStep $step): int
    {
        $type = $step->resolver_type;
        $value = $step->resolver_value;
        $count = 0;
        foreach ($this->resolver->resolve($data->companyId, $type, $value, $data->subjectType, $data->subjectId, $data->context) as $userId) {
            $taskId = (int) DB::table('workflow_tasks')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $data->companyId, 'workflow_instance_id' => $instanceId, 'workflow_step_id' => $step->id, 'assigned_user_id' => $userId, 'assignment_source' => $type.':'.$value, 'status' => 'pending', 'lock_version' => 1, 'due_at' => is_numeric($step->due_after_minutes ?? null) ? now()->addMinutes((int) $step->due_after_minutes) : null, 'escalate_at' => is_numeric($step->escalation_after_minutes ?? null) ? now()->addMinutes((int) $step->escalation_after_minutes) : null, 'created_at' => now(), 'updated_at' => now()]);
            $this->notifications->emit($data->companyId, $instanceId, 'workflow.task.assigned', ['task_id' => $taskId, 'recipient_id' => $userId, 'action' => 'assigned']);
            $count++;
        }

        return $count;
    }

    public function cancel(int $companyId, int $instanceId, int $actorId, string $key): void
    {
        DB::transaction(function () use ($companyId, $instanceId, $actorId, $key): void {
            $row = WorkflowInstance::query()->where('company_id', $companyId)->whereKey($instanceId)->lockForUpdate()->first();
            abort_if($row === null, 404);
            if ($row->engine_status === 'cancelled') {
                return;
            }
            abort_unless($row->engine_status === 'running', 422);
            DB::table('workflow_instances')->where('id', $instanceId)->update(['engine_status' => 'cancelled', 'status' => 'cancelled', 'lock_version' => DB::raw('lock_version+1'), 'updated_at' => now()]);
            DB::table('workflow_tasks')->where('workflow_instance_id', $instanceId)->whereIn('status', ['pending', 'claimed'])->update(['status' => 'cancelled', 'updated_at' => now()]);
            $this->history->append($companyId, $instanceId, null, $actorId, 'workflow.cancelled', 'running', 'cancelled', $key);
        }, 3);
    }

    /** @return array<string, scalar|null> */
    private function condition(?string $json): array
    {
        $decoded = json_decode($json ?? '{}', true);
        if (! is_array($decoded)) {
            return [];
        }
        $result = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
