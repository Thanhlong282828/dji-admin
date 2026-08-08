<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateList;

final readonly class GetCandidateListQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(public int $companyId, public array $filters = []) {}
}
