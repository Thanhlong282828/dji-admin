<?php

namespace Modules\Project\Application\Commands;

final readonly class AddProjectMemberCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $projectId, public array $data, public int $actorId) {}
}
