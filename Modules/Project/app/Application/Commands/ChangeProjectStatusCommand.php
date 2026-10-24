<?php

namespace Modules\Project\Application\Commands;

final readonly class ChangeProjectStatusCommand
{
    public function __construct(public int $companyId, public int $projectId, public string $status, public int $actorId) {}
}
