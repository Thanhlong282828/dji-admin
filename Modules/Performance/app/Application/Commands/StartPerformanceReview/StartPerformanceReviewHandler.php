<?php

namespace Modules\Performance\Application\Commands\StartPerformanceReview;

use Modules\Performance\Domain\Services\PerformanceReviewService;

final readonly class StartPerformanceReviewHandler
{
    public function __construct(private PerformanceReviewService $service) {}

    public function handle(StartPerformanceReviewCommand $c): int
    {
        return $this->service->start($c->data);
    }
}
