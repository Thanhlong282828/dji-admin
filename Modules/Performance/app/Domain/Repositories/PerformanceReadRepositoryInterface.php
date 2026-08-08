<?php

namespace Modules\Performance\Domain\Repositories;

use Modules\Performance\Application\DTOs\EmployeePerformanceViewModel;

interface PerformanceReadRepositoryInterface
{
    /** @return array{cycles:list<array<string,mixed>>,employees:list<array<string,mixed>>,stats:array<string,int|float>} */
    public function dashboard(int $companyId): array;

    public function employee360(int $companyId, int $employeeId): EmployeePerformanceViewModel;

    /** @return list<array<string,mixed>> */
    public function history(int $companyId, int $employeeId): array;

    /** @return list<array<string,mixed>> */
    public function goalTimeline(int $companyId, int $goalId): array;

    /** @return list<array<string,mixed>> */
    public function competencyProfile(int $companyId, int $employeeId): array;
}
