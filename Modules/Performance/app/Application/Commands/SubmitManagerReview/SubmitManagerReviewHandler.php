<?php

namespace Modules\Performance\Application\Commands\SubmitManagerReview;

use Modules\Performance\Domain\Services\PerformanceReviewService;

final readonly class SubmitManagerReviewHandler
{
    public function __construct(private PerformanceReviewService $service) {}

    public function handle(SubmitManagerReviewCommand $c): int
    {
        return $this->service->managerReview($c->data);
    }
}
