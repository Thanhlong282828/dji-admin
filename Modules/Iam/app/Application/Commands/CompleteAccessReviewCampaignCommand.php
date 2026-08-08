<?php

namespace Modules\Iam\Application\Commands;

final readonly class CompleteAccessReviewCampaignCommand
{
    public function __construct(public int $companyId, public int $reviewId, public int $actorId) {}
}
