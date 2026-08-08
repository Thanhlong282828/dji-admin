<?php

namespace Modules\Recruitment\Application\Queries\GetRecruitmentDashboard;

final readonly class GetRecruitmentDashboardQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(public int $companyId, public array $filters = []) {}
}
