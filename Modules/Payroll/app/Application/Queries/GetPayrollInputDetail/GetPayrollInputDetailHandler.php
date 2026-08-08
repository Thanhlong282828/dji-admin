<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputDetail;

use Modules\Payroll\Application\DTOs\PayrollInputOperationsViewModel;
use Modules\Payroll\Domain\Repositories\PayrollInputOperationsReadRepositoryInterface;

final readonly class GetPayrollInputDetailHandler
{
    public function __construct(private PayrollInputOperationsReadRepositoryInterface $repository) {}

    public function handle(GetPayrollInputDetailQuery $query): PayrollInputOperationsViewModel
    {
        return $this->repository->detail($query->companyId, $query->snapshotId);
    }
}
