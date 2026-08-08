<?php

namespace Modules\Payroll\Application\Queries\GetPayrollItemDetail;

use Modules\Payroll\Application\DTOs\PayrollItemDetailViewModel;
use Modules\Payroll\Domain\Repositories\PayrollDeliveryReadRepositoryInterface;

final readonly class GetPayrollItemDetailHandler
{
    public function __construct(private PayrollDeliveryReadRepositoryInterface $repository) {}

    public function handle(GetPayrollItemDetailQuery $query): PayrollItemDetailViewModel
    {
        return $this->repository->itemDetail($query->companyId, $query->runId, $query->itemId);
    }
}
