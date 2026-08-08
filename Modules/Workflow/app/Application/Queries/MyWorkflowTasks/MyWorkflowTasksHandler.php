<?php

namespace Modules\Workflow\Application\Queries\MyWorkflowTasks;

use Modules\Workflow\Application\DTOs\WorkflowDashboardViewModel;
use Modules\Workflow\Domain\Repositories\WorkflowReadRepositoryInterface;

final readonly class MyWorkflowTasksHandler
{
    public function __construct(private WorkflowReadRepositoryInterface $repository) {}

    public function handle(MyWorkflowTasksQuery $q): WorkflowDashboardViewModel
    {
        return $this->repository->dashboard($q->companyId, $q->actorId);
    }
}
