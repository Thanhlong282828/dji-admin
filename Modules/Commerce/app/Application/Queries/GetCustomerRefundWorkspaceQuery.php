<?php

namespace Modules\Commerce\Application\Queries;

final readonly class GetCustomerRefundWorkspaceQuery
{
    public function __construct(public int $companyId) {}
}
