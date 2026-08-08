<?php

namespace Modules\Workflow\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Workflow\Domain\Contracts\WorkflowActorResolverInterface;
use Modules\Workflow\Domain\Contracts\WorkflowCompletionHandlerInterface;
use Modules\Workflow\Domain\Models\WorkflowInstance;
use Modules\Workflow\Domain\Models\WorkflowStep;
use Modules\Workflow\Domain\Models\WorkflowTask;

final readonly class WorkflowTaskService
{
    public function __construct(private WorkflowActorResolverInterface $resolver, private WorkflowCompletionHandlerInterface $completion, private WorkflowHistoryService $history, private WorkflowRoutingService $routing, private WorkflowNotificationOutboxService $notifications) {}

    public function claim(int $companyId, int $taskId, int $actorId, int $version, string $key): int
    {
        return DB::transaction(function () use ($companyId, $taskId, $actorId, $version, $key): int {
            [$instance, $task] = $this->lockedInstanceAndTask($companyId, $taskId);
            abort_unless($instance->engine_status === 'running', 409, 'Workflow is not running.');
            if ($task->status === 'claimed' && $task->claimed_by === $actorId) {
                return $taskId;
            }
            abort_unless($task->status === 'pending' && $task->lock_version === $version, 409, 'Task version conflict.');
            abort_unless($task->assigned_user_id === $actorId, 403);
            DB::table('workflow_tasks')->where('id', $taskId)->update(['status' => 'claimed', 'claimed_by' => $actorId, 'claimed_at' => now(), 'lock_version' => $version + 1, 'updated_at' => now()]);
            $this->history->append($companyId, (int) $task->workflow_instance_id, $taskId, $actorId, 'task.claimed', 'pending', 'claimed', $key);

            return $taskId;
        }, 3);
    }

    public function decide(int $companyId, int $taskId, int $actorId, string $decision, ?string $reason, string $key): int
    {
        return DB::transaction(function () use ($companyId, $taskId, $actorId, $decision, $reason, $key): int {
            $existing = DB::table('workflow_decisions')->where('company_id', $companyId)->where('idempotency_key', $key)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            [$instance, $task] = $this->lockedInstanceAndTask($companyId, $taskId);
            abort_unless(in_array($task->status, ['pending', 'claimed'], true), 409, 'Task already decided.');
            abort_unless($task->assigned_user_id === $actorId || $task->claimed_by === $actorId, 403);
            if ($decision === 'rejected' && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages(['reason' => 'Rejection reason is required.']);
            }
            abort_unless($instance->engine_status === 'running', 409);
            $payload = ['task_id' => $taskId, 'actor_id' => $actorId, 'decision' => $decision, 'reason' => $reason];
            $decisionId = (int) DB::table('workflow_decisions')->insertGetId(['company_id' => $companyId, 'workflow_instance_id' => $instance->id, 'workflow_task_id' => $taskId, 'actor_id' => $actorId, 'decision' => $decision, 'reason' => $reason, 'idempotency_key' => $key, 'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'decided_at' => now()]);
            DB::table('workflow_tasks')->where('id', $taskId)->update(['status' => $decision, 'lock_version' => DB::raw('lock_version+1'), 'updated_at' => now()]);
            $this->history->append($companyId, (int) $instance->id, $taskId, $actorId, 'task.'.$decision, (string) $task->status, $decision, $key.'-history', ['reason' => $reason]);
            if ($decision === 'rejected') {
                $this->notifications->emit((int) $instance->company_id, (int) $instance->id, 'workflow.rejected', ['task_id' => $taskId, 'action' => 'rejected']);
                $this->finish($instance, $actorId, 'rejected', $key);

                return $decisionId;
            }
            $step = WorkflowStep::query()->find($task->workflow_step_id);
            abort_if($step === null, 422);
            $mode = $step->approval_mode;
            if ($mode === 'any_one') {
                DB::table('workflow_tasks')->where('workflow_instance_id', $instance->id)->where('workflow_step_id', $step->id)->whereIn('status', ['pending', 'claimed'])->update(['status' => 'cancelled', 'updated_at' => now()]);
                $this->advanceOrFinish($instance, $step, $actorId, $key);
            } elseif ($mode === 'all_must_approve' || $mode === 'parallel') {
                if (! DB::table('workflow_tasks')->where('workflow_instance_id', $instance->id)->where('workflow_step_id', $step->id)->whereNotIn('status', ['approved', 'cancelled'])->exists()) {
                    $this->advanceOrFinish($instance, $step, $actorId, $key);
                }
            } else {
                $this->advanceOrFinish($instance, $step, $actorId, $key);
            }

            return $decisionId;
        }, 3);
    }

    public function escalate(int $companyId, int $taskId, int $actorId, string $key): int
    {
        return DB::transaction(function () use ($companyId, $taskId, $actorId, $key): int {
            [$instance, $task] = $this->lockedInstanceAndTask($companyId, $taskId);
            if (! in_array($task->status, ['pending', 'claimed'], true)) {
                return $taskId;
            }
            $step = WorkflowStep::query()->find($task->workflow_step_id);
            abort_if($step === null, 422);
            $type = $step->fallback_resolver_type;
            $value = $step->fallback_resolver_value;
            if ($type !== null && $value !== null) {
                $users = $this->resolver->resolve($companyId, $type, $value, $instance->subject_type, $instance->subject_id, []);
                if ($users !== []) {
                    DB::table('workflow_tasks')->where('id', $taskId)->update(['assigned_user_id' => $users[0], 'assignment_source' => $type.':'.$value, 'escalation_level' => DB::raw('escalation_level+1'), 'updated_at' => now()]);
                    $this->notifications->emit($companyId, (int) $instance->id, 'workflow.task.escalated', ['task_id' => $taskId, 'recipient_id' => $users[0], 'action' => 'escalated']);
                }
            }
            $this->history->append($companyId, (int) $task->workflow_instance_id, $taskId, $actorId ?: null, 'task.escalated', (string) $task->status, (string) $task->status, $key, ['level' => (int) $task->escalation_level + 1]);

            return $taskId;
        }, 3);
    }

    private function advanceOrFinish(WorkflowInstance $instance, WorkflowStep $step, int $actorId, string $key): void
    {
        $context = $this->snapshotContext($instance->definition_snapshot);
        $next = WorkflowStep::query()
            ->where('workflow_definition_version_id', $step->workflow_definition_version_id)
            ->where('sequence', '>', $step->sequence)
            ->orderBy('sequence')
            ->get()
            ->first(fn (WorkflowStep $candidate): bool => $this->routing->matches($context, $this->condition($candidate->condition)));
        if ($next === null) {
            $this->finish($instance, $actorId, 'completed', $key);

            return;
        }
        $assignees = $this->resolver->resolve($instance->company_id, $next->resolver_type, $next->resolver_value, $instance->subject_type, $instance->subject_id, $context);
        if ($assignees === []) {
            throw ValidationException::withMessages(['assignee' => 'Next workflow step has no eligible assignee.']);
        }
        foreach ($assignees as $userId) {
            $taskId = (int) DB::table('workflow_tasks')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $instance->company_id, 'workflow_instance_id' => $instance->id, 'workflow_step_id' => $next->id, 'assigned_user_id' => $userId, 'assignment_source' => $next->resolver_type.':'.$next->resolver_value, 'status' => 'pending', 'lock_version' => 1, 'due_at' => is_numeric($next->due_after_minutes ?? null) ? now()->addMinutes((int) $next->due_after_minutes) : null, 'escalate_at' => is_numeric($next->escalation_after_minutes ?? null) ? now()->addMinutes((int) $next->escalation_after_minutes) : null, 'created_at' => now(), 'updated_at' => now()]);
            $this->notifications->emit((int) $instance->company_id, (int) $instance->id, 'workflow.task.assigned', ['task_id' => $taskId, 'recipient_id' => $userId, 'action' => 'assigned']);
        }
        DB::table('workflow_instances')->where('id', $instance->id)->update(['current_step' => $next->sequence, 'lock_version' => DB::raw('lock_version+1'), 'updated_at' => now()]);
    }

    private function finish(WorkflowInstance $instance, int $actorId, string $status, string $key): void
    {
        DB::table('workflow_instances')->where('id', $instance->id)->update(['engine_status' => $status, 'status' => $status === 'completed' ? 'approved' : 'rejected', 'completed_at' => now(), 'lock_version' => DB::raw('lock_version+1'), 'updated_at' => now()]);
        $this->history->append((int) $instance->company_id, (int) $instance->id, null, $actorId, 'workflow.'.$status, 'running', $status, $key.'-terminal');
        if ($status === 'completed') {
            $this->completion->completed((int) $instance->company_id, (int) $instance->id, (string) $instance->subject_type, (int) $instance->subject_id);
        }
    }

    /** @return array{WorkflowInstance, WorkflowTask} */
    private function lockedInstanceAndTask(int $companyId, int $taskId): array
    {
        $taskReference = WorkflowTask::query()->where('company_id', $companyId)->whereKey($taskId)->first();
        abort_if($taskReference === null, 404);
        $instance = WorkflowInstance::query()->where('company_id', $companyId)->whereKey($taskReference->workflow_instance_id)->lockForUpdate()->first();
        abort_if($instance === null, 404);
        $task = WorkflowTask::query()->where('company_id', $companyId)->where('workflow_instance_id', $instance->id)->whereKey($taskId)->lockForUpdate()->first();
        abort_if($task === null, 404);

        return [$instance, $task];
    }

    /** @return array<string, scalar|null> */
    private function snapshotContext(string $snapshotJson): array
    {
        $snapshot = json_decode($snapshotJson, true);
        if (! is_array($snapshot)) {
            return [];
        }
        $raw = $snapshot['context'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $context = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    /** @return array<string, scalar|null> */
    private function condition(?string $json): array
    {
        if ($json === null) {
            return [];
        }
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }
        $condition = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $condition[$key] = $value;
            }
        }

        return $condition;
    }
}
