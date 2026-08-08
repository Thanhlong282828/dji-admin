<?php

namespace Modules\Workflow\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Workflow\Application\DTOs\WorkflowDashboardViewModel;
use Modules\Workflow\Domain\Repositories\WorkflowReadRepositoryInterface;
use stdClass;

final class EloquentWorkflowReadRepository implements WorkflowReadRepositoryInterface
{
    public function dashboard(int $companyId, ?int $actorId = null): WorkflowDashboardViewModel
    {
        $definitions = $this->rows(DB::table('workflow_definitions')->where('company_id', $companyId)->select('id', 'code', 'name', 'status', 'latest_version', 'created_at')->latest('id')->limit(100)->get());
        $instances = $this->rows(DB::table('workflow_instances')->where('company_id', $companyId)->select('id', 'uuid', 'workflow_type', 'subject_type', 'subject_id', 'engine_status', 'current_step', 'due_at', 'started_at', 'completed_at')->latest('id')->limit(100)->get());
        $taskQuery = DB::table('workflow_tasks as t')->join('workflow_steps as s', 's.id', '=', 't.workflow_step_id')->where('t.company_id', $companyId);
        if ($actorId !== null) {
            $taskQuery->where('t.assigned_user_id', $actorId);
        }
        $tasks = $this->rows($taskQuery->select('t.id', 't.workflow_instance_id', 's.name as step_name', 't.status', 't.lock_version', 't.due_at', 't.escalate_at', 't.escalation_level')->latest('t.id')->limit(100)->get());

        return new WorkflowDashboardViewModel(count($definitions), DB::table('workflow_instances')->where('company_id', $companyId)->where('engine_status', 'running')->count(), DB::table('workflow_tasks')->where('company_id', $companyId)->whereIn('status', ['pending', 'claimed'])->count(), DB::table('workflow_tasks')->where('company_id', $companyId)->whereIn('status', ['pending', 'claimed'])->where('due_at', '<', now())->count(), $definitions, $instances, $tasks, []);
    }

    public function detail(int $companyId, int $instanceId): WorkflowDashboardViewModel
    {
        $vm = $this->dashboard($companyId);
        $instances = [];
        foreach ($vm->instances as $instance) {
            if (($instance['id'] ?? null) === $instanceId) {
                $instances[] = $instance;
            }
        }
        if ($instances === []) {
            abort(404);
        }
        $tasks = $this->rows(DB::table('workflow_tasks as t')->join('workflow_steps as s', 's.id', '=', 't.workflow_step_id')->where('t.company_id', $companyId)->where('t.workflow_instance_id', $instanceId)->select('t.id', 't.workflow_instance_id', 's.name as step_name', 's.sequence', 's.approval_mode', 't.assigned_user_id', 't.claimed_by', 't.status', 't.lock_version', 't.due_at', 't.escalate_at', 't.escalation_level')->orderBy('s.sequence')->get());
        $history = $this->rows(DB::table('workflow_histories')->where('company_id', $companyId)->where('workflow_instance_id', $instanceId)->select('id', 'workflow_task_id', 'actor_id', 'event', 'from_status', 'to_status', 'checksum', 'created_at')->orderBy('id')->get());

        return new WorkflowDashboardViewModel($vm->definitionCount, $vm->runningCount, $vm->pendingTaskCount, $vm->overdueTaskCount, $vm->definitions, $instances, $tasks, $history);
    }

    /**
     * @param  iterable<int, stdClass>  $source
     * @return list<array<string, scalar|null>>
     */
    private function rows(iterable $source): array
    {
        $out = [];
        foreach ($source as $row) {
            $item = [];
            foreach (get_object_vars($row) as $key => $value) {
                if (is_string($key) && (is_scalar($value) || $value === null)) {
                    $item[$key] = $value;
                }
            }
            $out[] = $item;
        }

        return $out;
    }
}
