<?php

namespace Modules\Recruitment\Application\DTOs;

use Modules\Recruitment\Domain\Enums\InterviewRecommendation;

final readonly class InterviewRecommendationSummary
{
    public function __construct(
        public InterviewRecommendation $recommendation,
        public int $count,
        public string $percentage,
    ) {}
}
