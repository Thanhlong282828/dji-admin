<?php

namespace Modules\Performance\Application\Commands\ApprovePerformanceReview;

use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class ApprovePerformanceReviewCommand
{
    public function __construct(public PerformanceActionData $data) {}
}
