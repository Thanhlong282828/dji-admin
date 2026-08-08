<?php

namespace Modules\Iam\Application\Commands;

final readonly class RejectWorkflowAccessRequestCommand
{
    public function __construct(public int $companyId, public int $requestId, public int $workflowInstanceId, public int $actorId) {}
}
