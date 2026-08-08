<?php

namespace Modules\Payroll\Application\Queries\GetPayrollInputCreateOptions;

use Modules\Payroll\Application\DTOs\PayrollInputOperationsViewModel;
use Modules\Payroll\Domain\Repositories\PayrollInputOperationsReadRepositoryInterface;

final readonly class GetPayrollInputCreateOptionsHandler
{
    public function __construct(private PayrollInputOperationsReadRepositoryInterface $repository) {}

    public function handle(GetPayrollInputCreateOptionsQuery $query): PayrollInputOperationsViewModel
    {
        return $this->repository->createOptions($query->companyId);
    }
}
