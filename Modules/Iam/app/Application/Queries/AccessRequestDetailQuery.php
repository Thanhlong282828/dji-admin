<?php

namespace Modules\Iam\Application\Queries;

final readonly class AccessRequestDetailQuery
{
    public function __construct(public int $companyId, public int $requestId) {}
}
