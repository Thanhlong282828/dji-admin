<?php

namespace Modules\Payroll\Application\Queries\GetPayrollPeriodDetail;

use Modules\Payroll\Application\DTOs\PayrollPeriodViewModel;
use Modules\Payroll\Domain\Repositories\PayrollPeriodReadRepositoryInterface;

final readonly class GetPayrollPeriodDetailHandler
{
    public function __construct(private PayrollPeriodReadRepositoryInterface $repository) {}

    public function handle(GetPayrollPeriodDetailQuery $query): PayrollPeriodViewModel
    {
        return $this->repository->detail($query->companyId, $query->periodId);
    }
}
