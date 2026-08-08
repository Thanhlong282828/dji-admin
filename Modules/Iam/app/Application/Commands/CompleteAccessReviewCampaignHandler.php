<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\AccessReviewService;

final readonly class CompleteAccessReviewCampaignHandler
{
    public function __construct(private AccessReviewService $service) {}

    public function handle(CompleteAccessReviewCampaignCommand $command): int
    {
        return $this->service->complete($command->companyId, $command->reviewId, $command->actorId);
    }
}
