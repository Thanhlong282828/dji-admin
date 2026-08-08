<?php

namespace Modules\Workflow\Application\Queries\WorkflowDefinitionList;

final readonly class WorkflowDefinitionListQuery
{
    public function __construct(public int $companyId) {}
}
