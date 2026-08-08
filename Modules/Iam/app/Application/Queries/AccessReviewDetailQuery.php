<?php

namespace Modules\Iam\Application\Queries;

final readonly class AccessReviewDetailQuery
{
    public function __construct(public int $companyId, public int $reviewId) {}
}
