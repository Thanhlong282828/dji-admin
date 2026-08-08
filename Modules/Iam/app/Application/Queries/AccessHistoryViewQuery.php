<?php

namespace Modules\Iam\Application\Queries;

final readonly class AccessHistoryViewQuery
{
    public function __construct(public int $companyId) {}
}
