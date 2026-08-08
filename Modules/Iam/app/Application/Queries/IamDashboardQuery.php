<?php

namespace Modules\Iam\Application\Queries;

final readonly class IamDashboardQuery
{
    public function __construct(public int $companyId) {}
}
