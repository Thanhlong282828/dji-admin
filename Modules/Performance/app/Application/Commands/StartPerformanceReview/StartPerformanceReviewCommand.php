<?php

namespace Modules\Performance\Application\Commands\StartPerformanceReview;

use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class StartPerformanceReviewCommand
{
    public function __construct(public PerformanceActionData $data) {}
}
