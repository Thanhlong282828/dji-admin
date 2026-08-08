<?php

namespace Modules\Workflow\Application\Queries\WorkflowInstanceDetail;

use Modules\Workflow\Application\DTOs\WorkflowDashboardViewModel;
use Modules\Workflow\Domain\Repositories\WorkflowReadRepositoryInterface;

final readonly class WorkflowInstanceDetailHandler
{
    public function __construct(private WorkflowReadRepositoryInterface $repository) {}

    public function handle(WorkflowInstanceDetailQuery $q): WorkflowDashboardViewModel
    {
        return $this->repository->detail($q->companyId, $q->instanceId);
    }
}
