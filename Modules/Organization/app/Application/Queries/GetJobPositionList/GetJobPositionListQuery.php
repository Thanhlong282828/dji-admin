<?php

namespace Modules\Organization\Application\Queries\GetJobPositionList;

final readonly class GetJobPositionListQuery
{
    /** @param array<string,mixed> $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
