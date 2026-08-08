<?php

namespace Modules\Recruitment\Application\Queries\GetInterviewPanelSummary;

final readonly class GetInterviewPanelSummaryQuery
{
    public function __construct(public int $companyId, public int $interviewId) {}
}
