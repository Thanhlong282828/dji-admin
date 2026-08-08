<?php

namespace Modules\Onboarding\Domain\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Onboarding\Domain\Models\OnboardingCase;

interface OnboardingReadRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, OnboardingCase>
     */
    public function cases(int $companyId, array $filters): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   preboarding: int, in_progress: int, probation_due: int,
     *   completed_month: int, overdue_items: int, pending_provisioning: int,
     *   status_counts: array<string, int>
     * }
     */
    public function dashboard(int $companyId, array $filters): array;
}
