<?php

namespace Modules\Attendance\Domain\Repositories;

use Modules\Attendance\Application\DTOs\OvertimeManagementViewModel;

interface OvertimeReadRepositoryInterface
{
    public function management(int $companyId): OvertimeManagementViewModel;
}
