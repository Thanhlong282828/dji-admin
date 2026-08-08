<?php

namespace Modules\Performance\Application\Commands\SubmitSelfReview;

use Modules\Performance\Domain\Services\PerformanceReviewService;

final readonly class SubmitSelfReviewHandler
{
    public function __construct(private PerformanceReviewService $service) {}

    public function handle(SubmitSelfReviewCommand $c): int
    {
        return $this->service->selfReview($c->data);
    }
}
