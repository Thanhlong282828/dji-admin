<?php

namespace Modules\Iam\Application\Commands;

final readonly class StartAccessReviewCampaignCommand
{
    public function __construct(public int $companyId, public int $reviewId, public int $actorId) {}
}
