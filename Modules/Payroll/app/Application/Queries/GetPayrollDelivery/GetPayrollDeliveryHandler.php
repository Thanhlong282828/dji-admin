<?php

namespace Modules\Payroll\Application\Queries\GetPayrollDelivery;

use Modules\Payroll\Application\DTOs\PayrollDeliveryViewModel;
use Modules\Payroll\Domain\Repositories\PayrollDeliveryReadRepositoryInterface;

final readonly class GetPayrollDeliveryHandler
{
    public function __construct(private PayrollDeliveryReadRepositoryInterface $repository) {}

    public function handle(GetPayrollDeliveryQuery $q): PayrollDeliveryViewModel
    {
        return $this->repository->dashboard($q->companyId);
    }
}
