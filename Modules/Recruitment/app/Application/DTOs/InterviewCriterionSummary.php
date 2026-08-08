<?php

namespace Modules\Recruitment\Application\DTOs;

final readonly class InterviewCriterionSummary
{
    public function __construct(
        public string $code,
        public string $label,
        public string $averagePercent,
        public string $averageRawScore,
        public int $maximumScore,
        public int $responseCount,
        public int $weight,
    ) {}
}
