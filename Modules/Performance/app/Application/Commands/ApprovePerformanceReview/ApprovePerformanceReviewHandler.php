<?php

namespace Modules\Performance\Application\Commands\ApprovePerformanceReview;

use Modules\Performance\Domain\Services\PerformanceReviewService;

final readonly class ApprovePerformanceReviewHandler
{
    public function __construct(private PerformanceReviewService $service) {}

    public function handle(ApprovePerformanceReviewCommand $c): int
    {
        return $this->service->approve($c->data);
    }
}
