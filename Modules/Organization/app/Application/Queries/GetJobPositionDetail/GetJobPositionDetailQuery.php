<?php

namespace Modules\Organization\Application\Queries\GetJobPositionDetail;

final readonly class GetJobPositionDetailQuery
{
    public function __construct(public int $companyId, public int $id) {}
}
