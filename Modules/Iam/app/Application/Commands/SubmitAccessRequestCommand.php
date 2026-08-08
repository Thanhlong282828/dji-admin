<?php

namespace Modules\Iam\Application\Commands;

final readonly class SubmitAccessRequestCommand
{
    public function __construct(public int $companyId, public int $requestId, public int $actorId, public ?int $workflowDefinitionId = null) {}
}
