<?php

namespace Modules\Employee\Application\Queries\LifecycleTimeline;

use Modules\Employee\Domain\Repositories\EmployeeLifecycleReadRepositoryInterface;

final readonly class LifecycleTimelineHandler
{
    public function __construct(private EmployeeLifecycleReadRepositoryInterface $repository) {}

    /** @return list<array<string,mixed>> */
    public function handle(LifecycleTimelineQuery $query): array
    {
        return $this->repository->timeline($query->companyId, $query->employeeId);
    }
}
