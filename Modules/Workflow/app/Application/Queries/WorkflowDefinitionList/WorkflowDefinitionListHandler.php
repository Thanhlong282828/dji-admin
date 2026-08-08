<?php

namespace Modules\Workflow\Application\Queries\WorkflowDefinitionList;

use Modules\Workflow\Application\DTOs\WorkflowDashboardViewModel;
use Modules\Workflow\Domain\Repositories\WorkflowReadRepositoryInterface;

final readonly class WorkflowDefinitionListHandler
{
    public function __construct(private WorkflowReadRepositoryInterface $repository) {}

    public function handle(WorkflowDefinitionListQuery $q): WorkflowDashboardViewModel
    {
        return $this->repository->dashboard($q->companyId);
    }
}
