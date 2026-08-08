<?php

namespace Modules\Performance\Application\Queries\EmployeePerformance360;

use Modules\Performance\Application\DTOs\EmployeePerformanceViewModel;
use Modules\Performance\Domain\Repositories\PerformanceReadRepositoryInterface;

final readonly class EmployeePerformance360Handler
{
    public function __construct(private PerformanceReadRepositoryInterface $repo) {}

    public function handle(EmployeePerformance360Query $q): EmployeePerformanceViewModel
    {
        return $this->repo->employee360($q->companyId, $q->employeeId);
    }
}
