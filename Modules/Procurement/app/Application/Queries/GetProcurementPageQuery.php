<?php

namespace Modules\Procurement\Application\Queries;

final readonly class GetProcurementPageQuery
{
    public function __construct(public int $companyId, public string $page) {}
}
