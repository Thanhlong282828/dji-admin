<?php

namespace Modules\Payroll\Application\Queries\GetPaymentAccountList;

use Modules\Payroll\Application\DTOs\PaymentAccountViewModel;
use Modules\Payroll\Domain\Repositories\PaymentReadRepositoryInterface;

final readonly class GetPaymentAccountListHandler
{
    public function __construct(private PaymentReadRepositoryInterface $repository) {}

    public function handle(GetPaymentAccountListQuery $q): PaymentAccountViewModel
    {
        return $this->repository->accounts($q->companyId, $q->employeeId);
    }
}
