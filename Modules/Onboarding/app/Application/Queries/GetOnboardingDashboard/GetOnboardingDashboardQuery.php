<?php

namespace Modules\Onboarding\Application\Queries\GetOnboardingDashboard;

final readonly class GetOnboardingDashboardQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(public int $companyId, public array $filters = []) {}
}
