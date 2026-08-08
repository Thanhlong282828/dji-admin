<?php

namespace Modules\Employee\Application\Queries\GetEmployeeDetail;

use Modules\Employee\Domain\Repositories\EmployeeReadRepositoryInterface;

final readonly class GetEmployeeDetailHandler
{
    public function __construct(private EmployeeReadRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(GetEmployeeDetailQuery $q): array
    {
        return $this->repo->detail($q->companyId, $q->id, $q->sensitive);
    }
}
