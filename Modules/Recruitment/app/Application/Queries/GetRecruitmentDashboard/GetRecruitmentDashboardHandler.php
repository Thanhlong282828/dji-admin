<?php

namespace Modules\Recruitment\Application\Queries\GetRecruitmentDashboard;

use Modules\Recruitment\Domain\Repositories\RecruitmentReadRepositoryInterface;

final readonly class GetRecruitmentDashboardHandler
{
    public function __construct(private RecruitmentReadRepositoryInterface $repo) {}

    /**
     * @return array{
     *   open_requisitions: int, pending_approval: int, total_vacancies: int,
     *   applications: int, screening: int, interview: int, offers: int,
     *   hired: int, rejected: int, stage_counts: array<string, int>
     * }
     */
    public function handle(GetRecruitmentDashboardQuery $q): array
    {
        return $this->repo->dashboard($q->companyId, $q->filters);
    }
}
