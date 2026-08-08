<?php

namespace Modules\Workflow\Domain\Services;

use Modules\Workflow\Domain\Models\WorkflowTask;

final readonly class WorkflowSlaService
{
    public function __construct(private WorkflowTaskService $tasks) {}

    public function escalateOverdue(): int
    {
        $count = 0;
        foreach (WorkflowTask::query()->whereIn('status', ['pending', 'claimed'])->whereNotNull('escalate_at')->where('escalate_at', '<=', now())->orderBy('id')->limit(500)->get() as $task) {
            $this->tasks->escalate($task->company_id, $task->id, 0, 'scheduled-escalation-'.$task->id.'-'.($task->escalation_level + 1));
            $count++;
        }

        return $count;
    }
}
