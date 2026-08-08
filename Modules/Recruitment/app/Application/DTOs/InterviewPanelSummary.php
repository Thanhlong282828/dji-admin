<?php

namespace Modules\Recruitment\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class InterviewPanelSummary
{
    /**
     * @param  list<InterviewRecommendationSummary>  $recommendations
     * @param  list<InterviewCriterionSummary>  $criteria
     * @param  list<string>  $strengths
     * @param  list<string>  $concerns
     */
    public function __construct(
        public int $interviewId,
        public int $companyId,
        public int $applicationId,
        public int $assignedCount,
        public int $submittedCount,
        public int $draftCount,
        public int $pendingCount,
        public string $completionPercent,
        public ?string $averageScore,
        public ?string $minimumScore,
        public ?string $maximumScore,
        public array $recommendations,
        public array $criteria,
        public array $strengths,
        public array $concerns,
        public ?CarbonImmutable $lastSubmittedAt,
        public int $interviewVersion,
    ) {}
}
