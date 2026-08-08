<?php

namespace Modules\Workflow\Application\Queries\WorkflowTimeline;

use Modules\Workflow\Application\DTOs\WorkflowDashboardViewModel;
use Modules\Workflow\Domain\Repositories\WorkflowReadRepositoryInterface;

final readonly class WorkflowTimelineHandler
{
    public function __construct(private WorkflowReadRepositoryInterface $repository) {}

    public function handle(WorkflowTimelineQuery $q): WorkflowDashboardViewModel
    {
        return $this->repository->detail($q->companyId, $q->instanceId);
    }
}
