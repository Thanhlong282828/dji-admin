<?php

namespace Modules\Performance\Application\Queries\CompetencyProfile;

final readonly class CompetencyProfileQuery
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
