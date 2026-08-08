<?php

namespace Modules\Recruitment\Application\Queries\GetInterviewList;

final readonly class GetInterviewListQuery
{
    public function __construct(public int $companyId) {}
}
