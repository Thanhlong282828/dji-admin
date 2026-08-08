<?php

namespace Modules\Recruitment\Application\Queries\GetRecruitmentRequisitionList;

final readonly class GetRecruitmentRequisitionListQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(public int $companyId, public array $filters = []) {}
}
