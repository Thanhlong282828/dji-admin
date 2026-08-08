<?php

namespace Modules\Workflow\Application\Queries\MyWorkflowTasks;

final readonly class MyWorkflowTasksQuery
{
    public function __construct(public int $companyId, public int $actorId) {}
}
