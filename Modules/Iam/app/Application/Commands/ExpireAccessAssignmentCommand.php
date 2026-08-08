<?php

namespace Modules\Iam\Application\Commands;

final readonly class ExpireAccessAssignmentCommand
{
    public function __construct(public int $companyId, public int $assignmentId, public int $actorId) {}
}
