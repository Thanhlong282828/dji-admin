<?php

namespace Modules\Employee\Application\Queries\EmploymentHistory;

use Modules\Employee\Domain\Repositories\EmployeeLifecycleReadRepositoryInterface;

final readonly class EmploymentHistoryHandler
{
    public function __construct(private EmployeeLifecycleReadRepositoryInterface $repository) {}

    /** @return list<array<string,mixed>> */
    public function handle(EmploymentHistoryQuery $query): array
    {
        return $this->repository->employmentHistory($query->companyId, $query->employeeId);
    }
}
