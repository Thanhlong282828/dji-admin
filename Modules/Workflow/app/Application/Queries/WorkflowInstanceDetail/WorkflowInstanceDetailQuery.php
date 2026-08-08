<?php

namespace Modules\Workflow\Application\Queries\WorkflowInstanceDetail;

final readonly class WorkflowInstanceDetailQuery
{
    public function __construct(public int $companyId, public int $instanceId) {}
}
