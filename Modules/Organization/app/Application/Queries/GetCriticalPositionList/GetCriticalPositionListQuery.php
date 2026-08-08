<?php

namespace Modules\Organization\Application\Queries\GetCriticalPositionList;

final readonly class GetCriticalPositionListQuery
{
    public function __construct(public int $companyId) {}
}
