<?php

namespace Modules\Payroll\Application\Queries\GetPayslipList;

use Modules\Payroll\Application\DTOs\PayslipViewModel;
use Modules\Payroll\Domain\Repositories\PayslipReadRepositoryInterface;

final readonly class GetPayslipListHandler
{
    public function __construct(private PayslipReadRepositoryInterface $repository) {}

    public function handle(GetPayslipListQuery $q): PayslipViewModel
    {
        return $this->repository->list($q->companyId, $q->filters);
    }
}
