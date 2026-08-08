<?php

namespace Modules\Attendance\Application\Queries;

use Modules\Attendance\Application\DTOs\WorkforceManagementViewModel;
use Modules\Attendance\Domain\Repositories\WorkforceReadRepositoryInterface;

final readonly class GetWorkforceManagementHandler
{
    public function __construct(private WorkforceReadRepositoryInterface $repository) {}

    public function handle(GetWorkforceManagementQuery $query): WorkforceManagementViewModel
    {
        return $this->repository->management($query->companyId, $query->section, $query->search);
    }
}
