<?php

namespace Modules\Procurement\Application\Queries;

final readonly class GetProcurementPageQuery
{
    /** @param array<string,mixed> $filters */
    public function __construct(public int $companyId, public string $page, public array $filters = []) {}
}
