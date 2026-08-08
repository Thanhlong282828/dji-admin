<?php

namespace Modules\Performance\Application\Commands\SubmitSelfReview;

use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class SubmitSelfReviewCommand
{
    public function __construct(public PerformanceActionData $data) {}
}
