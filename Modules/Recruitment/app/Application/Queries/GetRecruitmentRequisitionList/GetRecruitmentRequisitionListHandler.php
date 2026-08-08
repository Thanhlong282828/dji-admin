<?php

namespace Modules\Recruitment\Application\Queries\GetRecruitmentRequisitionList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;
use Modules\Recruitment\Domain\Repositories\RecruitmentReadRepositoryInterface;

final readonly class GetRecruitmentRequisitionListHandler
{
    public function __construct(private RecruitmentReadRepositoryInterface $repo) {}

    /** @return LengthAwarePaginator<int, RecruitmentRequisition> */
    public function handle(GetRecruitmentRequisitionListQuery $q): LengthAwarePaginator
    {
        return $this->repo->requisitions($q->companyId, $q->filters);
    }
}
