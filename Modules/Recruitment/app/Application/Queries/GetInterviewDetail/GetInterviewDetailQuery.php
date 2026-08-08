<?php

namespace Modules\Recruitment\Application\Queries\GetInterviewDetail;

final readonly class GetInterviewDetailQuery
{
    public function __construct(public int $companyId, public int $interviewId) {}
}
