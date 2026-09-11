<?php

namespace Modules\Project\Application\Queries;

final readonly class GetProjectsQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(public int $companyId, public ?int $projectId = null, public array $filters = []) {}
}
