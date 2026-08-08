<?php

namespace Modules\Workflow\Application\DTOs;

final readonly class WorkflowDashboardViewModel
{
    /**
     * @param  list<array<string, scalar|null>>  $definitions
     * @param  list<array<string, scalar|null>>  $instances
     * @param  list<array<string, scalar|null>>  $tasks
     * @param  list<array<string, scalar|null>>  $history
     */
    public function __construct(public int $definitionCount, public int $runningCount, public int $pendingTaskCount, public int $overdueTaskCount, public array $definitions, public array $instances, public array $tasks, public array $history) {}
}
