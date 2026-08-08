<?php

namespace Modules\Workflow\Application\Queries\WorkflowAuditView;

use Modules\Workflow\Application\DTOs\WorkflowDashboardViewModel;
use Modules\Workflow\Domain\Repositories\WorkflowReadRepositoryInterface;

final readonly class WorkflowAuditViewHandler
{
    public function __construct(private WorkflowReadRepositoryInterface $repository) {}

    public function handle(WorkflowAuditViewQuery $q): WorkflowDashboardViewModel
    {
        return $this->repository->detail($q->companyId, $q->instanceId);
    }
}
