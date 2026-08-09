<?php

namespace Modules\Dashboard\Application\DTOs;

/**
 * @phpstan-type DashboardPayload array{
 *     totalEmployees: int,
 *     activeEmployees: int,
 *     pendingLeaveRequests: int,
 *     openRecruitmentRequisitions: int,
 *     totalArticles: int,
 *     publishedArticles: int,
 *     draftArticles: int,
 *     featuredArticles: int,
 *     headcountByStatus: array<string, int>,
 *     recentActivities: list<array<string, mixed>>
 * }
 */
final readonly class DashboardViewModel
{
    /**
     * @param  array<string, int>  $headcountByStatus
     * @param  list<array<string, mixed>>  $recentActivities
     */
    public function __construct(public int $totalEmployees, public int $activeEmployees, public int $pendingLeaveRequests, public int $openRecruitmentRequisitions, public int $totalArticles, public int $publishedArticles, public int $draftArticles, public int $featuredArticles, public array $headcountByStatus, public array $recentActivities) {}

    /** @return DashboardPayload */
    public function toArray(): array
    {
        return [
            'totalEmployees' => $this->totalEmployees,
            'activeEmployees' => $this->activeEmployees,
            'pendingLeaveRequests' => $this->pendingLeaveRequests,
            'openRecruitmentRequisitions' => $this->openRecruitmentRequisitions,
            'totalArticles' => $this->totalArticles,
            'publishedArticles' => $this->publishedArticles,
            'draftArticles' => $this->draftArticles,
            'featuredArticles' => $this->featuredArticles,
            'headcountByStatus' => $this->headcountByStatus,
            'recentActivities' => $this->recentActivities,
        ];
    }

    /** @param DashboardPayload $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['totalEmployees'],
            $payload['activeEmployees'],
            $payload['pendingLeaveRequests'],
            $payload['openRecruitmentRequisitions'],
            $payload['totalArticles'],
            $payload['publishedArticles'],
            $payload['draftArticles'],
            $payload['featuredArticles'],
            $payload['headcountByStatus'],
            $payload['recentActivities'],
        );
    }
}
