<?php

namespace Modules\Organization\Application\Queries\GetOrganizationTree;

final readonly class GetOrganizationTreeQuery
{
    /** @param array<string,mixed> $filters */
    public function __construct(public int $companyId, public array $filters = []) {}
}
