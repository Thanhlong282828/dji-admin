<?php

namespace Modules\Attendance\Application\Queries;

use Modules\Attendance\Application\DTOs\OvertimeManagementViewModel;
use Modules\Attendance\Domain\Repositories\OvertimeReadRepositoryInterface;

final readonly class GetOvertimeManagementHandler
{
    public function __construct(private OvertimeReadRepositoryInterface $repository) {}

    public function handle(GetOvertimeManagementQuery $query): OvertimeManagementViewModel
    {
        return $this->repository->management($query->companyId);
    }
}
