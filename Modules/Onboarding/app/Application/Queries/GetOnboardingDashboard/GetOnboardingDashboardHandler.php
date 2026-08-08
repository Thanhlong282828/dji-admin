<?php

namespace Modules\Onboarding\Application\Queries\GetOnboardingDashboard;

use Modules\Onboarding\Domain\Repositories\OnboardingReadRepositoryInterface;

final readonly class GetOnboardingDashboardHandler
{
    public function __construct(private OnboardingReadRepositoryInterface $repo) {}

    /**
     * @return array{
     *   preboarding: int, in_progress: int, probation_due: int,
     *   completed_month: int, overdue_items: int, pending_provisioning: int,
     *   status_counts: array<string, int>
     * }
     */
    public function handle(GetOnboardingDashboardQuery $q): array
    {
        return $this->repo->dashboard($q->companyId, $q->filters);
    }
}
