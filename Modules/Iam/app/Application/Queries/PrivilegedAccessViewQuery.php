<?php

namespace Modules\Iam\Application\Queries;

final readonly class PrivilegedAccessViewQuery
{
    public function __construct(public int $companyId) {}
}
