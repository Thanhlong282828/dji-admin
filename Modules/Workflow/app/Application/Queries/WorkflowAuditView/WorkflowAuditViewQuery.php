<?php

namespace Modules\Workflow\Application\Queries\WorkflowAuditView;

final readonly class WorkflowAuditViewQuery
{
    public function __construct(public int $companyId, public int $instanceId) {}
}
