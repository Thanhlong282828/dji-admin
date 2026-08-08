<?php

namespace Modules\Onboarding\Application\Queries\GetOnboardingCaseList;

final readonly class GetOnboardingCaseListQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(public int $companyId, public array $filters = []) {}
}
