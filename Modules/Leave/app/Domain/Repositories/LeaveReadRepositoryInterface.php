<?php

namespace Modules\Leave\Domain\Repositories;

use Modules\Leave\Application\DTOs\LeaveManagementViewModel;

interface LeaveReadRepositoryInterface
{
    public function management(int $companyId): LeaveManagementViewModel;
}
