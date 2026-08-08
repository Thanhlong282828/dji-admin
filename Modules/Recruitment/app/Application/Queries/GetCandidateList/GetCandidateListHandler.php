<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Repositories\RecruitmentReadRepositoryInterface;

final readonly class GetCandidateListHandler
{
    public function __construct(private RecruitmentReadRepositoryInterface $repo) {}

    /** @return LengthAwarePaginator<int, Candidate> */
    public function handle(GetCandidateListQuery $q): LengthAwarePaginator
    {
        return $this->repo->candidates($q->companyId, $q->filters);
    }
}
