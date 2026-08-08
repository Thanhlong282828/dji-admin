<?php

namespace Modules\Employee\Domain\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Employee\Application\DTOs\EmployeeLifecycleViewModel;

interface EmployeeLifecycleReadRepositoryInterface
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function paginate(int $companyId, ?string $search, ?string $state, int $perPage): LengthAwarePaginator;

    public function employee360(int $companyId, int $employeeId): EmployeeLifecycleViewModel;

    /** @return list<array<string, mixed>> */
    public function employmentHistory(int $companyId, int $employeeId): array;

    /** @return list<array<string, mixed>> */
    public function timeline(int $companyId, int $employeeId): array;
}
