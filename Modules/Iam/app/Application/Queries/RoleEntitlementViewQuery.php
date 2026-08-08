<?php

namespace Modules\Iam\Application\Queries;

final readonly class RoleEntitlementViewQuery
{
    public function __construct(public int $companyId) {}
}
