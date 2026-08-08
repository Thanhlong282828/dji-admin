<?php

namespace Modules\Iam\Application\Queries;

final readonly class AccessRequestListQuery
{
    public function __construct(public int $companyId) {}
}
