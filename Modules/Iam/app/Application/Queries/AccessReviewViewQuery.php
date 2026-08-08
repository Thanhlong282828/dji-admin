<?php

namespace Modules\Iam\Application\Queries;

final readonly class AccessReviewViewQuery
{
    public function __construct(public int $companyId) {}
}
