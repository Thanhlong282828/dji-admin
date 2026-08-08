<?php

namespace Modules\Leave\Application\Queries;

use Modules\Leave\Application\DTOs\LeaveManagementViewModel;
use Modules\Leave\Domain\Repositories\LeaveReadRepositoryInterface;

final readonly class GetLeaveManagementHandler
{
    public function __construct(private LeaveReadRepositoryInterface $repository) {}

    public function handle(GetLeaveManagementQuery $query): LeaveManagementViewModel
    {
        return $this->repository->management($query->companyId);
    }
}
