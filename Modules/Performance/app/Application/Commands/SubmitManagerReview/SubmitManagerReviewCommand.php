<?php

namespace Modules\Performance\Application\Commands\SubmitManagerReview;

use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class SubmitManagerReviewCommand
{
    public function __construct(public PerformanceActionData $data) {}
}
