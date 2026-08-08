<?php

namespace Modules\Workflow\Application\Queries\WorkflowTimeline;

final readonly class WorkflowTimelineQuery
{
    public function __construct(public int $companyId, public int $instanceId) {}
}
