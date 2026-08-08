<?php

namespace Modules\Iam\Application\Commands;

final readonly class DecideAccessReviewItemCommand
{
    public function __construct(public int $companyId, public int $reviewId, public int $itemId, public int $reviewerId, public string $decision, public string $reason, public int $expectedVersion) {}
}
