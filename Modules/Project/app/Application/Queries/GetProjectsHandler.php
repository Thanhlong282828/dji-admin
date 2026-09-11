<?php

namespace Modules\Project\Application\Queries;

use Modules\Project\Application\Services\ProjectReadService;

final readonly class GetProjectsHandler
{
    public function __construct(private ProjectReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetProjectsQuery $query): array
    {
        return $this->service->read($query->companyId, $query->projectId, $query->filters);
    }
}
