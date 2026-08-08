<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\AccessReviewService;

final readonly class CreateAccessReviewCampaignHandler
{
    public function __construct(private AccessReviewService $service) {}

    public function handle(CreateAccessReviewCampaignCommand $command): int
    {
        return $this->service->create($command->companyId, $command->name, $command->scopeType, $command->scopeId, $command->dueDate, $command->actorId, $command->idempotencyKey);
    }
}
