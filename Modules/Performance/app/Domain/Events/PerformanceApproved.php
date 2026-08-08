<?php

namespace Modules\Performance\Domain\Events;

final readonly class PerformanceApproved
{
    public function __construct(public int $companyId, public int $reviewId) {}
}
