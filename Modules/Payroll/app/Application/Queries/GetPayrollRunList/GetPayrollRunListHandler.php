<?php

namespace Modules\Payroll\Application\Queries\GetPayrollRunList;

use Modules\Payroll\Application\DTOs\PayrollRunListViewModel;
use Modules\Payroll\Domain\Repositories\PayrollDeliveryReadRepositoryInterface;

final readonly class GetPayrollRunListHandler
{
    public function __construct(private PayrollDeliveryReadRepositoryInterface $repository) {}

    public function handle(GetPayrollRunListQuery $query): PayrollRunListViewModel
    {
        return $this->repository->runList($query->companyId, $query->filters);
    }
}
