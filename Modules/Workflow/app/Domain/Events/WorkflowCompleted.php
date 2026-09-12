<?php

namespace Modules\Workflow\Domain\Events;

final readonly class WorkflowCompleted
{
    public function __construct(
        public int $companyId,
        public int $instanceId,
        public string $subjectType,
        public int $subjectId,
    ) {}
}
