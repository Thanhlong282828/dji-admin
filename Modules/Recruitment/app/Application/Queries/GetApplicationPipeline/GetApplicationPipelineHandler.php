<?php

namespace Modules\Recruitment\Application\Queries\GetApplicationPipeline;

use Modules\Recruitment\Domain\Repositories\RecruitmentReadRepositoryInterface;

final readonly class GetApplicationPipelineHandler
{
    public function __construct(private RecruitmentReadRepositoryInterface $repo) {}

    /** @return array<string, list<array<string, mixed>>> */
    public function handle(GetApplicationPipelineQuery $q): array
    {
        return $this->repo->pipeline($q->companyId, $q->requisitionId);
    }
}
