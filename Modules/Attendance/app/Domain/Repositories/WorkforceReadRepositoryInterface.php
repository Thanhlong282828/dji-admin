<?php

namespace Modules\Attendance\Domain\Repositories;

use Modules\Attendance\Application\DTOs\WorkforceManagementViewModel;

interface WorkforceReadRepositoryInterface
{
    public function management(int $companyId, string $section, ?string $search): WorkforceManagementViewModel;
}
