<?php

namespace Modules\Performance\Application\Queries\EmployeeCareer360;

use Modules\Performance\Domain\Repositories\CareerReadRepositoryInterface;

final readonly class EmployeeCareer360Handler
{
    public function __construct(private CareerReadRepositoryInterface $repo) {}

    /** @return array<string,mixed> */
    public function handle(EmployeeCareer360Query $q): array
    {
        return $this->repo->employee360($q->companyId, $q->employeeId);
    }
}
