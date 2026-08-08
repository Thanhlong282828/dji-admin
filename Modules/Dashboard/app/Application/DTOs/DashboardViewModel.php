<?php

namespace Modules\Dashboard\Application\DTOs;

final readonly class DashboardViewModel
{
    /** @param array<string,int> $headcountByStatus @param list<array<string,mixed>> $recentActivities */
    public function __construct(public int $totalEmployees, public int $activeEmployees, public int $pendingLeaveRequests, public int $openRecruitmentRequisitions, public int $totalArticles, public int $publishedArticles, public int $draftArticles, public int $featuredArticles, public array $headcountByStatus, public array $recentActivities) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
