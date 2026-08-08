<?php

namespace Modules\Onboarding\Application\Queries\GetOnboardingCaseList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Onboarding\Domain\Models\OnboardingCase;
use Modules\Onboarding\Domain\Repositories\OnboardingReadRepositoryInterface;

final readonly class GetOnboardingCaseListHandler
{
    public function __construct(private OnboardingReadRepositoryInterface $repo) {}

    /** @return LengthAwarePaginator<int, OnboardingCase> */
    public function handle(GetOnboardingCaseListQuery $q): LengthAwarePaginator
    {
        return $this->repo->cases($q->companyId, $q->filters);
    }
}
