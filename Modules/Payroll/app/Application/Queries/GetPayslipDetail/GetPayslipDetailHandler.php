<?php

namespace Modules\Payroll\Application\Queries\GetPayslipDetail;

use Modules\Payroll\Application\DTOs\PayslipViewModel;
use Modules\Payroll\Domain\Repositories\PayslipReadRepositoryInterface;

final readonly class GetPayslipDetailHandler
{
    public function __construct(private PayslipReadRepositoryInterface $repository) {}

    public function handle(GetPayslipDetailQuery $q): PayslipViewModel
    {
        return $this->repository->detail($q->companyId, $q->payslipId);
    }
}
