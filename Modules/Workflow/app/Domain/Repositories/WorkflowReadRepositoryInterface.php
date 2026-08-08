<?php

namespace Modules\Workflow\Domain\Repositories;

use Modules\Workflow\Application\DTOs\WorkflowDashboardViewModel;

interface WorkflowReadRepositoryInterface
{
    public function dashboard(int $companyId, ?int $actorId = null): WorkflowDashboardViewModel;

    public function detail(int $companyId, int $instanceId): WorkflowDashboardViewModel;
}
