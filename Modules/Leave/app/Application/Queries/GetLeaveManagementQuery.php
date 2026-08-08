<?php

namespace Modules\Leave\Application\Queries;

final readonly class GetLeaveManagementQuery
{
    public function __construct(public int $companyId) {}
}
