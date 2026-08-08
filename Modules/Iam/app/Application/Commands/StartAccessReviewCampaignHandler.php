<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\AccessReviewService;

final readonly class StartAccessReviewCampaignHandler
{
    public function __construct(private AccessReviewService $service) {}

    public function handle(StartAccessReviewCampaignCommand $command): void
    {
        $this->service->start($command->companyId, $command->reviewId, $command->actorId);
    }
}
