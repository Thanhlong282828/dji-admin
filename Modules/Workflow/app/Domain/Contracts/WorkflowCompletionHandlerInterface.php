<?php

namespace Modules\Workflow\Domain\Contracts;

interface WorkflowCompletionHandlerInterface
{
    public function completed(int $companyId, int $instanceId, string $subjectType, int $subjectId): void;
}
