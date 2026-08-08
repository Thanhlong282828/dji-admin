<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\AccessReviewService;

final readonly class DecideAccessReviewItemHandler
{
    public function __construct(private AccessReviewService $service) {}

    public function handle(DecideAccessReviewItemCommand $command): void
    {
        $this->service->decide($command->companyId, $command->reviewId, $command->itemId, $command->reviewerId, $command->decision, $command->reason, $command->expectedVersion);
    }
}
