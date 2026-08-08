<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputList;

use Modules\Payroll\Application\DTOs\PayrollInputOperationsViewModel;
use Modules\Payroll\Domain\Repositories\PayrollInputOperationsReadRepositoryInterface;

final readonly class GetPayrollInputListHandler
{
    public function __construct(private PayrollInputOperationsReadRepositoryInterface $repository) {}

    public function handle(GetPayrollInputListQuery $query): PayrollInputOperationsViewModel
    {
        return $this->repository->listing($query->companyId, $query->filters);
    }
}
