<?php

namespace Modules\Employee\Application\Queries\Employee360View;

use Modules\Employee\Application\DTOs\EmployeeLifecycleViewModel;
use Modules\Employee\Domain\Repositories\EmployeeLifecycleReadRepositoryInterface;

final readonly class Employee360ViewHandler
{
    public function __construct(private EmployeeLifecycleReadRepositoryInterface $repository) {}

    public function handle(Employee360ViewQuery $query): EmployeeLifecycleViewModel
    {
        return $this->repository->employee360($query->companyId, $query->employeeId);
    }
}
