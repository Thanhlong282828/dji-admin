<?php

namespace Modules\Iam\Application\Queries;

final readonly class UserAccess360Query
{
    public function __construct(public int $companyId, public int $userId) {}
}
