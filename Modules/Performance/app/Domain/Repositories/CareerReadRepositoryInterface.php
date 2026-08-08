<?php

namespace Modules\Performance\Domain\Repositories;

use Modules\Performance\Application\DTOs\CareerDashboardViewModel;

interface CareerReadRepositoryInterface
{
    public function dashboard(int $companyId): CareerDashboardViewModel;

    /** @return array<string,mixed> */
    public function employee360(int $companyId, int $employeeId): array;

    /** @return list<array<string,mixed>> */
    public function timeline(int $companyId, int $employeeId): array;

    /** @return list<array<string,mixed>> */
    public function talentPool(int $companyId): array;
}
