<?php

namespace Modules\Recruitment\Application\Queries\GetInterviewDetail;

use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Services\ApplicationInterviewService;

final readonly class GetInterviewDetailHandler
{
    public function __construct(private ApplicationInterviewService $service) {}

    public function handle(GetInterviewDetailQuery $query): ApplicationInterview
    {
        return $this->service->detail($query->companyId, $query->interviewId);
    }
}
