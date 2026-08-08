<?php

namespace Modules\Recruitment\Application\Queries\GetInterviewList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Services\ApplicationInterviewService;

final readonly class GetInterviewListHandler
{
    public function __construct(private ApplicationInterviewService $service) {}

    /** @return LengthAwarePaginator<int, ApplicationInterview> */
    public function handle(GetInterviewListQuery $query): LengthAwarePaginator
    {
        return $this->service->list($query->companyId);
    }
}
