<?php

namespace Modules\Dashboard\Domain\Repositories;

use Modules\Dashboard\Application\DTOs\DashboardViewModel;

interface DashboardReadRepositoryInterface
{
    public function overview(?int $companyId): DashboardViewModel;
}
