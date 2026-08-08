<?php

namespace Modules\Payroll\Application\Queries\GetPayrollRunDetail;

use Modules\Payroll\Application\DTOs\PayrollRunDetailViewModel;
use Modules\Payroll\Domain\Repositories\PayrollDeliveryReadRepositoryInterface;

final readonly class GetPayrollRunDetailHandler
{
    public function __construct(private PayrollDeliveryReadRepositoryInterface $repository) {}

    public function handle(GetPayrollRunDetailQuery $q): PayrollRunDetailViewModel
    {
        return $this->repository->runDetail($q->companyId, $q->runId);
    }
}
