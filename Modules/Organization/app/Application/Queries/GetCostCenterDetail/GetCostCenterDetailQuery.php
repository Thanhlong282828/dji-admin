<?php

namespace Modules\Organization\Application\Queries\GetCostCenterDetail;

final readonly class GetCostCenterDetailQuery
{
    public function __construct(public int $companyId, public int $id) {}
}
